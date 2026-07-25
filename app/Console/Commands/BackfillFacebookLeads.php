<?php

namespace App\Console\Commands;

use App\Models\Integration;
use App\Models\LeadStage;
use App\Services\Crm\FacebookSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * One-off historical import, meant for go-live.
 *
 * Kept separate from the routine sync because the intent is different: these
 * enquiries are mostly months old and already dealt with. By default they are
 * imported unassigned, already read, and parked in a closed stage — so the
 * team wakes up to their real pipeline, not thousands of stale "new" leads.
 */
class BackfillFacebookLeads extends Command
{
    protected $signature = 'leads:backfill
        {--integration= : Limit to one integration id}
        {--since= : Only leads created on or after this date (Y-m-d)}
        {--stage= : Lead stage id to park them in, e.g. a closed stage}
        {--assign : Distribute them across the mapped members}
        {--unread : Leave them unread so they raise the notification bell}
        {--pages=200 : Safety rail on pages of 100 per integration}
        {--force : Skip the confirmation prompt}';

    protected $description = 'Import historical Facebook leads (run once at go-live)';

    public function handle(FacebookSyncService $sync): int
    {
        $integrations = Integration::query()
            ->where('provider', 'facebook')
            ->when($this->option('integration'), fn ($q, $id) => $q->whereKey($id))
            ->get();

        if ($integrations->isEmpty()) {
            $this->error('No Facebook integrations found.');

            return self::FAILURE;
        }

        $since = $this->option('since') ? Carbon::parse($this->option('since')) : null;
        $stageId = $this->option('stage') ? (int) $this->option('stage') : null;
        $assign = (bool) $this->option('assign');
        $markRead = ! $this->option('unread');

        if ($stageId && ! LeadStage::whereKey($stageId)->exists()) {
            $this->error("Lead stage {$stageId} does not exist.");

            return self::FAILURE;
        }

        $this->table(['Setting', 'Value'], [
            ['Integrations', $integrations->count()],
            ['From', $since?->toDateString() ?? 'the beginning'],
            ['Stage', $stageId ? LeadStage::find($stageId)->name : 'the integration default'],
            ['Assign to members', $assign ? 'yes' : 'no — left unassigned'],
            ['Mark as read', $markRead ? 'yes' : 'no — will raise the bell'],
        ]);

        if (! $this->option('force') && ! $this->confirm('This can import thousands of leads. Continue?', false)) {
            return self::SUCCESS;
        }

        $totals = ['imported' => 0, 'skipped' => 0];

        foreach ($integrations as $integration) {
            $this->line("\n<info>{$integration->name}</info>");

            try {
                $result = $sync->sync($integration, [
                    'since' => $since,
                    'stage_id' => $stageId,
                    'assign' => $assign,
                    'mark_read' => $markRead,
                    'max_pages' => (int) $this->option('pages'),
                ]);

                $totals['imported'] += $result['imported'];
                $totals['skipped'] += $result['skipped'];

                $this->line("  imported {$result['imported']}, skipped {$result['skipped']}"
                    .($result['complete'] ? '' : '  — page limit hit, run again to continue'));
            } catch (Throwable $e) {
                $this->error('  '.$e->getMessage());
            }
        }

        $this->info("\nDone. Imported {$totals['imported']}, skipped {$totals['skipped']} already present.");

        return self::SUCCESS;
    }
}
