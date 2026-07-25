<?php

namespace App\Console\Commands;

use App\Models\Integration;
use App\Services\Crm\FacebookSyncService;
use Illuminate\Console\Command;
use Throwable;

class SyncFacebookLeads extends Command
{
    protected $signature = 'leads:sync {--integration= : Limit to one integration id}';

    protected $description = 'Pull new leads from every active Facebook integration';

    public function handle(FacebookSyncService $sync): int
    {
        $integrations = Integration::query()
            ->where('provider', 'facebook')
            ->where('status', 'active')
            ->when($this->option('integration'), fn ($q, $id) => $q->whereKey($id))
            ->get();

        if ($integrations->isEmpty()) {
            $this->info('No active Facebook integrations.');

            return self::SUCCESS;
        }

        $failed = 0;

        foreach ($integrations as $integration) {
            try {
                $result = $sync->sync($integration);

                $this->line(sprintf(
                    '%-28s imported %d, skipped %d%s',
                    mb_strimwidth($integration->name, 0, 27, '…'),
                    $result['imported'],
                    $result['skipped'],
                    $result['complete'] ? '' : '  (more remain)'
                ));
            } catch (Throwable $e) {
                // One broken integration must not stop the others.
                $failed++;
                $this->error("{$integration->name}: {$e->getMessage()}");
            }
        }

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
