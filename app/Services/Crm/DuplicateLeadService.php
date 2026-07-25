<?php

namespace App\Services\Crm;

use App\Models\Customer;
use App\Models\Lead;
use App\Services\Support\SettingsService;
use App\Support\Settings;
use Illuminate\Support\Carbon;

/**
 * Decides whether an arriving enquiry repeats one we already hold.
 *
 * The rule, as agreed with the client:
 *
 *   – Same person, SAME campaign, within the window  → repeat
 *   – Same person, DIFFERENT campaign                → fresh
 *   – Earlier lead already won or lost               → fresh
 *   – Outside the window                             → fresh
 *
 * "Same campaign" means the same integration, not the same campaign *name*.
 * The name is Facebook's form title: two integrations can share one, and
 * renaming the form on Facebook would silently change it underneath us.
 *
 * "Same person" is whatever CustomerService resolved to — mobile first, email
 * second — so both identifiers are honoured with mobile taking precedence.
 */
class DuplicateLeadService
{
    public function __construct(private SettingsService $settings) {}

    /** How long a repeat stays a repeat. */
    public function windowDays(): int
    {
        $days = $this->settings->get(
            Settings::DUPLICATE_WINDOW_DAYS,
            Settings::DEFAULT_DUPLICATE_WINDOW_DAYS,
        );

        return max(0, (int) $days);
    }

    /**
     * The earlier lead this one repeats, or null when it is fresh.
     *
     * @param  int|null  $ignoreLeadId  the lead being judged, when it already exists
     */
    public function findOriginal(
        Customer $customer,
        ?int $integrationId,
        Carbon $arrivedAt,
        ?int $ignoreLeadId = null,
    ): ?Lead {
        // No campaign to compare against — a CSV row or a phone enquiry cannot
        // repeat "the same form", so it is always fresh.
        if ($integrationId === null) {
            return null;
        }

        $window = $this->windowDays();

        if ($window === 0) {
            return null;
        }

        return Lead::query()
            ->where('customer_id', $customer->id)
            ->where('integration_id', $integrationId)
            ->when($ignoreLeadId, fn ($q) => $q->whereKeyNot($ignoreLeadId))
            /*
             | Strictly earlier. A backfill imports newest-first, so without
             | this the third enquiry would claim the first as its repeat and
             | the whole chain would come out inverted.
             */
            ->where('created_at', '<', $arrivedAt)
            ->where('created_at', '>=', $arrivedAt->copy()->subDays($window))
            /*
             | A won or lost lead is finished business. The client's call: the
             | same person coming back after that is a fresh opportunity worth
             | working, not a duplicate to be folded away.
             */
            ->whereDoesntHave('stage', fn ($q) => $q->whereIn('type', ['FINAL_POSITIVE', 'FINAL_NEGATIVE']))
            ->latest('created_at')
            ->first();
    }

    /**
     * Recompute the flag for every lead belonging to these customers.
     *
     * Needed after a backfill: Graph hands leads back newest-first, so at the
     * moment each row is written its earlier siblings may not exist yet, and
     * the flag set then would be wrong.
     *
     * @param  array<int>  $customerIds
     * @return int leads whose flag changed
     */
    public function repairFor(array $customerIds): int
    {
        if ($customerIds === []) {
            return 0;
        }

        $changed = 0;

        Lead::query()
            ->whereIn('customer_id', $customerIds)
            ->with('customer')
            ->orderBy('created_at')
            ->chunkById(500, function ($leads) use (&$changed) {
                foreach ($leads as $lead) {
                    if (! $lead->customer) {
                        continue;
                    }

                    $original = $this->findOriginal(
                        $lead->customer,
                        $lead->integration_id,
                        $lead->created_at,
                        $lead->id,
                    );

                    $isExisting = $original !== null;

                    if ($lead->is_existing === $isExisting && $lead->duplicate_of_id === $original?->id) {
                        continue;
                    }

                    $lead->forceFill([
                        'is_existing' => $isExisting,
                        'duplicate_of_id' => $original?->id,
                    ])->save();

                    $changed++;
                }
            });

        return $changed;
    }
}
