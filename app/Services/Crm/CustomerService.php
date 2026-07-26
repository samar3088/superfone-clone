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
    public function resolve(string $mobile, ?string $email, string $name, ?string $city = null): Customer
    {
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
            return DB::transaction(function () use ($name, $mobile, $email, $city) {
                $customer = Customer::create([
                    'name' => $name,
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
                    'team_id', 'created_by', 'name', 'city', 'notes', 'website',
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
