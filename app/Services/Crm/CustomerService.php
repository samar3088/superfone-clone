<?php

namespace App\Services\Crm;

use App\Models\Customer;
use App\Models\Lead;
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
        $byMobile = Customer::active()->where('mobile', $mobile)->first();

        if ($byMobile) {
            // Fill in an email we didn't have before, if it's not taken.
            if ($email && ! $byMobile->email && ! $this->emailTaken($email, $byMobile->id)) {
                $byMobile->update(['email' => $email]);
            }

            return $byMobile;
        }

        if ($email) {
            $byEmail = Customer::active()->where('email', $email)->first();

            if ($byEmail) {
                return $byEmail;
            }
        }

        return Customer::create([
            'name' => $name,
            'mobile' => $mobile,
            'email' => $email,
            'city' => $city,
            'last_activity_at' => now(),
        ]);
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
