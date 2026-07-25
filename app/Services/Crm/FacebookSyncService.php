<?php

namespace App\Services\Crm;

use App\LeadSources\FacebookLeadSource;
use App\Models\Integration;
use App\Models\Lead;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Pulls leads from a connected Facebook lead form into the CRM.
 *
 * Every run is recorded on the integration's Logs tab, whether it succeeds or
 * fails, so a broken token is visible rather than silent.
 */
class FacebookSyncService
{
    public function __construct(
        private FacebookLeadSource $facebook,
        private LeadService $leads,
    ) {}

    /**
     * Routine sync: everything since the last successful run.
     *
     * @param  array{stage_id?: int|null, assign?: bool, mark_read?: bool, since?: Carbon|null, max_pages?: int|null}  $options
     * @return array{fetched: int, imported: int, skipped: int, complete: bool}
     */
    public function sync(Integration $integration, array $options = []): array
    {
        if ($integration->status !== 'active') {
            return $this->skip($integration, 'Skipped — integration is paused.');
        }

        if (! $integration->isConfigured()) {
            return $this->skip($integration, 'Skipped — finish the settings first.');
        }

        /*
         | array_key_exists, not ??: the backfill passes since = null to mean
         | "everything ever", and ?? would quietly swap that for the 30-day
         | development window — importing nothing at all on an older form.
         */
        $since = array_key_exists('since', $options)
            ? $options['since']
            : $this->windowStart($integration);
        $imported = 0;
        $skipped = 0;

        try {
            $result = $this->facebook->eachLeadPage(
                $integration->external_form_id,
                $integration->external_page_id,
                $since?->timestamp ? (string) $since->timestamp : null,
                function (array $leads) use ($integration, $options, &$imported, &$skipped): void {
                    // One query per page instead of one per lead.
                    $existing = Lead::whereIn('external_id', array_column($leads, 'external_id'))
                        ->pluck('external_id')
                        ->flip();

                    foreach ($leads as $lead) {
                        if ($existing->has($lead['external_id'])) {
                            $skipped++;

                            continue;
                        }

                        $this->leads->intake($integration, $lead, $options);
                        $imported++;
                    }
                },
                maxPages: $options['max_pages'] ?? null,
            );
        } catch (Throwable $e) {
            $this->log($integration, 'error', 'Sync failed: '.$e->getMessage(), $imported);

            throw $e;
        }

        /*
         | Only move the watermark when the window was drained. Stopping on the
         | page limit and stamping "now" anyway would skip every lead we had
         | not reached yet — silently, and permanently.
         */
        if ($result['complete']) {
            $integration->forceFill(['last_synced_at' => now()])->save();
        }

        $this->log(
            $integration,
            $result['complete'] ? 'success' : 'warning',
            $this->summary($imported, $skipped, $result),
            $imported
        );

        return [
            'fetched' => $result['rows'],
            'imported' => $imported,
            'skipped' => $skipped,
            'complete' => $result['complete'],
        ];
    }

    /**
     * Where this run should start reading from.
     *
     * After the first successful sync that is simply the last run. Before it,
     * the configured lookback window keeps development to recent leads rather
     * than years of history.
     */
    private function windowStart(Integration $integration): ?Carbon
    {
        if ($integration->last_synced_at) {
            return $integration->last_synced_at;
        }

        $days = (int) config('facebook.sync_since_days');

        return $days > 0 ? now()->subDays($days) : null;
    }

    private function summary(int $imported, int $skipped, array $result): string
    {
        if (! $result['complete']) {
            return "Imported {$imported} lead(s), stopped at the page limit — "
                .'more remain, the next run continues from here.';
        }

        if ($imported === 0) {
            return $skipped > 0
                ? "No new leads — {$skipped} already imported."
                : 'No new leads since the last sync.';
        }

        return "Imported {$imported} new lead(s).";
    }

    private function skip(Integration $integration, string $message): array
    {
        $this->log($integration, 'warning', $message, 0);

        return ['fetched' => 0, 'imported' => 0, 'skipped' => 0, 'complete' => true];
    }

    private function log(Integration $integration, string $status, string $message, int $count): void
    {
        $integration->logs()->create([
            'status' => $status,
            'message' => $message,
            'leads_fetched' => $count,
        ]);
    }
}
