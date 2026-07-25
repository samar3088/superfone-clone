<?php

namespace App\Console\Commands;

use App\Models\Lead;
use App\Services\Crm\LeadService;
use Illuminate\Console\Command;

/**
 * Sends the lead alerts whose configured delay has now elapsed.
 *
 * A campaign can ask to be told some time after a lead lands rather than the
 * instant it does — this is what makes that happen.
 */
class SendDueLeadNotifications extends Command
{
    protected $signature = 'leads:notify';

    protected $description = 'Send lead notifications that have come due';

    public function handle(LeadService $leads): int
    {
        $sent = 0;
        $skipped = 0;

        Lead::query()
            ->whereNull('notified_at')
            ->whereNotNull('notify_at')
            ->where('notify_at', '<=', now())
            ->with(['assignee', 'stage'])
            ->orderBy('notify_at')
            // Bounded: a backlog should drain over several runs rather than
            // tie up one long request.
            ->limit(200)
            ->get()
            ->each(function (Lead $lead) use ($leads, &$sent, &$skipped) {
                $leads->deliverNotification($lead) ? $sent++ : $skipped++;
            });

        if ($sent === 0 && $skipped === 0) {
            $this->info('Nothing due.');

            return self::SUCCESS;
        }

        $this->info("Queued {$sent} notification(s), skipped {$skipped}.");

        return self::SUCCESS;
    }
}
