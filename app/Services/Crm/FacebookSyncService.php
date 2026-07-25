<?php

namespace App\Services\Crm;

use App\LeadSources\FacebookLeadSource;
use App\Models\Integration;
use App\Models\Lead;
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
     * @return array{fetched: int, imported: int, skipped: int}
     */
    public function sync(Integration $integration, ?int $limit = 100): array
    {
        if ($integration->status !== 'active') {
            return $this->log($integration, 'warning', 'Skipped — integration is paused.', 0);
        }

        if (! $integration->isConfigured()) {
            return $this->log($integration, 'warning', 'Skipped — finish the settings first.', 0);
        }

        try {
            $rows = $this->facebook->fetchLeads(
                $integration->external_form_id,
                $integration->external_page_id,
                // Only ask for leads newer than the last successful sync.
                $integration->last_synced_at?->timestamp ? (string) $integration->last_synced_at->timestamp : null,
                $limit,
            );
        } catch (Throwable $e) {
            $this->log($integration, 'error', 'Sync failed: '.$e->getMessage(), 0);

            throw $e;
        }

        $imported = 0;
        $skipped = 0;

        foreach ($rows as $row) {
            // The unique external_id makes a repeated sync a no-op.
            if (Lead::where('external_id', $row['external_id'])->exists()) {
                $skipped++;

                continue;
            }

            $this->leads->intake($integration, $row);
            $imported++;
        }

        $integration->forceFill(['last_synced_at' => now()])->save();

        $this->log(
            $integration,
            'success',
            $imported > 0
                ? "Imported {$imported} new lead(s)."
                : 'No new leads since the last sync.',
            $imported
        );

        return ['fetched' => count($rows), 'imported' => $imported, 'skipped' => $skipped];
    }

    private function log(Integration $integration, string $status, string $message, int $count): array
    {
        $integration->logs()->create([
            'status' => $status,
            'message' => $message,
            'leads_fetched' => $count,
        ]);

        return ['fetched' => $count, 'imported' => $count, 'skipped' => 0];
    }
}
