<?php

namespace App\Console\Commands;

use App\Models\Lead;
use App\Services\Crm\DuplicateLeadService;
use Illuminate\Console\Command;

/**
 * Recompute which leads repeat an earlier enquiry.
 *
 * Needed whenever leads arrive out of chronological order, or the window in
 * Settings changes — the flag is stored, so an existing lead does not re-judge
 * itself just because the rule moved.
 */
class RepairDuplicateLeads extends Command
{
    protected $signature = 'leads:repair-duplicates {--customer= : Limit to one customer id}';

    protected $description = 'Re-check which leads repeat an earlier enquiry';

    public function handle(DuplicateLeadService $duplicates): int
    {
        $customerIds = $this->option('customer')
            ? [(int) $this->option('customer')]
            : Lead::query()->distinct()->pluck('customer_id')->filter()->all();

        if ($customerIds === []) {
            $this->info('No leads to check.');

            return self::SUCCESS;
        }

        $this->line(sprintf(
            'Checking %d customer(s) against a %d-day window…',
            count($customerIds),
            $duplicates->windowDays(),
        ));

        $changed = $duplicates->repairFor($customerIds);

        $this->info("Corrected {$changed} lead(s).");

        return self::SUCCESS;
    }
}
