<?php

namespace App\Services\Crm;

use App\Models\Customer;
use App\Models\CustomerChannel;
use App\Models\Lead;
use App\Models\Team;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CustomerService
{
    /**
     * Find the customer behind an enquiry, or create one.
     *
     * Mobile and email are both unique, so the same person arriving through a
     * second campaign resolves to the existing record — a new lead, not a new
     * customer.
     *
     * If the mobile matches one customer and the email another, the mobile
     * wins (it is the stronger identifier here) and the collision is left for
     * an owner to resolve with a merge.
     */
    public function resolve(
        string $mobile,
        ?string $email,
        string $name,
        ?string $city = null,
        // Passed only when the source gave both halves — a Facebook form with
        // separate first and last fields. Otherwise the model derives them.
        ?string $firstName = null,
        ?string $lastName = null,
    ): Customer {
        /*
         | Searched across every number and address a customer holds, not just
         | the primary one. Someone who first enquired on one number and later
         | rings from a second is the same person, and matching only the primary
         | would quietly split them into two records.
         */
        $byMobile = $this->findByChannel(CustomerChannel::PHONE, $mobile);

        if ($byMobile) {
            // Learn an address we did not have, provided nobody else holds it.
            if ($email) {
                $this->attachChannel($byMobile, CustomerChannel::EMAIL, $email);
            }

            return $byMobile;
        }

        if ($email) {
            $byEmail = $this->findByChannel(CustomerChannel::EMAIL, $email);

            if ($byEmail) {
                // Mobile is the stronger identifier here, so a new number on a
                // known address is recorded against them.
                $this->attachChannel($byEmail, CustomerChannel::PHONE, $mobile);

                return $byEmail;
            }
        }

        try {
            return DB::transaction(function () use ($name, $mobile, $email, $city, $firstName, $lastName) {
                $customer = Customer::create([
                    'name' => $name,
                    ...($firstName && $lastName
                        ? ['first_name' => $firstName, 'last_name' => $lastName]
                        : []),
                    'mobile' => $mobile,
                    'email' => $email,
                    'city' => $city,
                    'last_activity_at' => now(),
                ]);

                $this->attachChannel($customer, CustomerChannel::PHONE, $mobile, primary: true);

                if ($email) {
                    $this->attachChannel($customer, CustomerChannel::EMAIL, $email, primary: true);
                }

                return $customer;
            });
        } catch (UniqueConstraintViolationException) {
            /*
             | Two syncs running at once can both miss the lookup and then race
             | to insert the same person. The unique index settles it; the loser
             | simply adopts the row that won.
             */
            return $this->findByChannel(CustomerChannel::PHONE, $mobile)
                ?? Customer::active()->where('mobile', $mobile)->firstOrFail();
        }
    }

    /** The customer holding this number or address, if anyone does. */
    public function findByChannel(string $type, ?string $value): ?Customer
    {
        $value = $value === null ? null : CustomerChannel::normalise($type, $value);

        if ($value === null) {
            return null;
        }

        return Customer::active()
            ->whereHas('channels', fn (Builder $q) => $q->where('type', $type)->where('value', $value))
            ->first();
    }

    /**
     * Record another way of reaching this customer.
     *
     * Silently does nothing if the value is unusable, or if someone else
     * already holds it — one number cannot belong to two people, and the
     * collision is a merge decision rather than something to resolve here.
     */
    public function attachChannel(Customer $customer, string $type, ?string $value, bool $primary = false): ?CustomerChannel
    {
        $value = $value === null ? null : CustomerChannel::normalise($type, $value);

        if ($value === null) {
            return null;
        }

        $existing = CustomerChannel::where('type', $type)->where('value', $value)->first();

        if ($existing) {
            return $existing->customer_id === $customer->id ? $existing : null;
        }

        $channel = CustomerChannel::create([
            'customer_id' => $customer->id,
            'type' => $type,
            'value' => $value,
            'is_primary' => $primary,
        ]);

        // Keep the denormalised primary on the customer in step, so lists and
        // exports still read one number per row without a join.
        $column = $type === CustomerChannel::PHONE ? 'mobile' : 'email';

        if ($primary || blank($customer->{$column})) {
            $customer->forceFill([$column => $value])->save();
        }

        return $channel;
    }

    /**
     * Fold one or more duplicates into a target customer. Their leads and
     * calls move across; the duplicates are kept as tombstones pointing at the
     * survivor so nothing is silently lost.
     *
     * @param  array<int>  $duplicateIds
     */
    public function merge(Customer $target, array $duplicateIds): Customer
    {
        $duplicates = Customer::active()
            ->whereIn('id', $duplicateIds)
            ->whereKeyNot($target->id)
            ->get();

        if ($duplicates->isEmpty()) {
            throw ValidationException::withMessages([
                'customers' => 'Select at least one different customer to merge in.',
            ]);
        }

        return DB::transaction(function () use ($target, $duplicates) {
            foreach ($duplicates as $duplicate) {
                $duplicate->leads()->update(['customer_id' => $target->id]);
                $duplicate->calls()->update(['customer_id' => $target->id]);
                // Notes travel too. Left behind they would follow the tombstone
                // out of every list and be unreachable — the one thing on a
                // contact that only a person can have written.
                $duplicate->notes()->update(['customer_id' => $target->id]);

                /*
                 | Their numbers and addresses come too, demoted from primary —
                 | the survivor keeps its own. Without this the merged-away
                 | numbers would stop matching and the next enquiry from one of
                 | them would create a third record.
                 */
                $duplicate->channels()->update([
                    'customer_id' => $target->id,
                    'is_primary' => false,
                ]);

                // Keep details the survivor is missing rather than discarding them.
                $target->fill([
                    'email' => $target->email ?: $duplicate->email,
                    'city' => $target->city ?: $duplicate->city,
                    'notes' => trim((string) $target->notes."\n".(string) $duplicate->notes) ?: null,
                ]);

                // The unique indexes must not collide with the tombstone.
                $duplicate->forceFill([
                    'mobile' => $duplicate->mobile.'+merged'.$duplicate->id,
                    'email' => $duplicate->email ? $duplicate->email.'+merged'.$duplicate->id : null,
                    'merged_into_id' => $target->id,
                    'merged_at' => now(),
                ])->save();

                $duplicate->delete();
            }

            $target->last_activity_at = now();
            $target->save();

            activity('customer')
                ->performedOn($target)
                ->withProperties(['merged_ids' => $duplicates->pluck('id')->all()])
                ->log('Merged '.$duplicates->count().' customer(s) into this record');

            return $target->fresh();
        });
    }

    /**
     * Create a contact by hand, with as many numbers and addresses as they gave.
     *
     * The first phone and the first email are the primary pair; the rest are
     * recorded alongside and count for duplicate detection just the same. If any
     * of them already belongs to someone, that person is returned instead of a
     * second record being made — which is the whole point of the channel table.
     *
     * @return array{customer: Customer, existing: bool}
     */
    public function createManual(array $data): array
    {
        $phones = $this->cleanList(CustomerChannel::PHONE, $data['phones'] ?? []);
        $emails = $this->cleanList(CustomerChannel::EMAIL, $data['emails'] ?? []);

        if ($phones === []) {
            throw ValidationException::withMessages([
                'phones.0' => 'Enter a valid 10-digit mobile number.',
            ]);
        }

        // Anyone already holding one of these is this person.
        foreach ([[CustomerChannel::PHONE, $phones], [CustomerChannel::EMAIL, $emails]] as [$type, $values]) {
            foreach ($values as $value) {
                if ($found = $this->findByChannel($type, $value)) {
                    return ['customer' => $this->enrich($found, $data, $phones, $emails), 'existing' => true];
                }
            }
        }

        $customer = DB::transaction(function () use ($data, $phones, $emails) {
            $customer = Customer::create([
                ...collect($data)->only([
                    'team_id', 'created_by', 'name', 'first_name', 'last_name',
                    'city', 'notes', 'website',
                    'business_name', 'house_no', 'address_1', 'address_2', 'additional_info',
                ])->all(),
                // Falls back to the only organisation, so a contact is never
                // orphaned just because the form did not offer a choice.
                'team_id' => $data['team_id'] ?? Team::query()->orderBy('id')->value('id'),
                'mobile' => $phones[0],
                'email' => $emails[0] ?? null,
                'last_activity_at' => now(),
            ]);

            $this->syncChannels($customer, $phones, $emails);

            return $customer;
        });

        activity('customer')->performedOn($customer)->log('Created contact');

        return ['customer' => $customer, 'existing' => false];
    }

    /** Add anything new to a customer we already had, without overwriting. */
    private function enrich(Customer $customer, array $data, array $phones, array $emails): Customer
    {
        foreach ($phones as $value) {
            $this->attachChannel($customer, CustomerChannel::PHONE, $value);
        }

        foreach ($emails as $value) {
            $this->attachChannel($customer, CustomerChannel::EMAIL, $value);
        }

        // Fill blanks only. What is already recorded was presumably checked.
        $fill = collect($data)
            ->only(['city', 'website', 'business_name', 'house_no', 'address_1', 'address_2', 'additional_info'])
            ->filter(fn ($v, $k) => filled($v) && blank($customer->{$k}))
            ->all();

        if ($fill !== []) {
            $customer->forceFill($fill)->save();
        }

        return $customer->fresh();
    }

    private function syncChannels(Customer $customer, array $phones, array $emails): void
    {
        foreach ($phones as $i => $value) {
            $this->attachChannel($customer, CustomerChannel::PHONE, $value, primary: $i === 0);
        }

        foreach ($emails as $i => $value) {
            $this->attachChannel($customer, CustomerChannel::EMAIL, $value, primary: $i === 0);
        }
    }

    /** Normalise, drop anything unusable, and keep the order given. */
    private function cleanList(string $type, array $values): array
    {
        return collect($values)
            ->map(fn ($v) => is_string($v) ? CustomerChannel::normalise($type, $v) : null)
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /** Suggest likely duplicates: same mobile prefix or a shared email. */
    public function findPotentialDuplicates(Customer $customer)
    {
        return Customer::active()
            ->whereKeyNot($customer->id)
            ->where(function ($q) use ($customer) {
                $q->where('name', 'like', '%'.$customer->name.'%');

                if ($customer->email) {
                    $q->orWhere('email', $customer->email);
                }
            })
            ->limit(10)
            ->get(['id', 'name', 'mobile', 'email']);
    }

    /**
     * Contacts that look like the same person, grouped.
     *
     * Two contacts can never share a phone number or an email address — the
     * channels table has a unique index on the value, so the obvious kind of
     * duplicate cannot exist. What is left is the same person entered twice
     * under two different numbers: a typo, a second SIM, a walk-in added by
     * hand who was already on file from a campaign.
     *
     * So the only thing that can pair them is the name, and a name alone is
     * weak evidence. Groups are ranked by what else agrees, and nothing is ever
     * merged without someone looking — a wrong merge is not undoable from the
     * screen, and one of those costs more than ten duplicates left alone.
     *
     * @return array<int, array{key: string, confidence: string, reason: string, customers: array<int, array<string, mixed>>}>
     */
    public function duplicateGroups(int $limit = 50): array
    {
        /*
         | One indexed pass to find which names repeat, then one query for the
         | rows in those groups. The alternative — comparing every contact with
         | every other — is 78 million comparisons at the 12,500 contacts
         | waiting to be imported.
         */
        $keys = Customer::active()
            ->whereNotNull('name_key')
            ->where('name_key', '!=', '')
            ->groupBy('name_key')
            ->havingRaw('count(*) > 1')
            ->limit($limit)
            ->pluck('name_key');

        if ($keys->isEmpty()) {
            return [];
        }

        return Customer::active()
            ->whereIn('name_key', $keys)
            ->withCount(['leads', 'calls', 'notes'])
            ->orderBy('name_key')
            ->orderBy('id')
            ->get(['id', 'name_key', 'name', 'mobile', 'email', 'city',
                'business_name', 'created_at', 'last_activity_at'])
            ->groupBy('name_key')
            ->map(fn ($group) => $this->describeGroup($group))
            ->values()
            ->sortBy(fn (array $g) => match ($g['confidence']) {
                'high' => 0, 'medium' => 1, default => 2,
            })
            ->values()
            ->all();
    }

    /**
     * How much to trust one group, and why — the "why" being the part someone
     * actually reads before deciding.
     */
    private function describeGroup($group): array
    {
        $cities = $group->pluck('city')->filter()->unique();
        $businesses = $group->pluck('business_name')->filter()->unique();

        // Same name and the same business, or the same name and the same town.
        // Neither is proof; both are more than a name on its own.
        [$confidence, $reason] = match (true) {
            $businesses->count() === 1 && $group->whereNotNull('business_name')->count() > 1 => ['high', 'Same name and the same business'],

            $cities->count() === 1 && $group->whereNotNull('city')->count() > 1 => ['medium', 'Same name and the same city'],

            default => ['low', 'Same name only — check before merging'],
        };

        return [
            'key' => (string) $group->first()->name_key,
            'confidence' => $confidence,
            'reason' => $reason,
            'customers' => $group->map(fn (Customer $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'mobile' => $c->mobile,
                'email' => $c->email,
                'city' => $c->city,
                'business_name' => $c->business_name,
                'leads_count' => $c->leads_count,
                'calls_count' => $c->calls_count,
                'notes_count' => $c->notes_count,
                'created_at' => $c->created_at?->toDateString(),
            ])->values()->all(),
        ];
    }

    private function emailTaken(string $email, int $ignoreId): bool
    {
        return Customer::active()->where('email', $email)->whereKeyNot($ignoreId)->exists();
    }

    /** Refresh the "last seen" stamp whenever a lead or call lands. */
    public function touchActivity(Customer $customer): void
    {
        $customer->forceFill(['last_activity_at' => now()])->save();
    }

    /** All leads for a customer, newest first, with stage and owner. */
    public function leadHistory(Customer $customer)
    {
        return Lead::query()
            ->where('customer_id', $customer->id)
            ->with(['stage:id,name,type,emoji', 'assignee:id,name', 'integration:id,name'])
            ->latest('id')
            ->get();
    }
}
