<?php

namespace App\Telephony;

use App\Models\LiveCall;
use App\Models\TeamMember;

/**
 * Offline driver: all telephony actions are pure state changes.
 * Lets the whole product work with zero provider account.
 */
class SimulatedProvider implements TelephonyProvider
{
    public function name(): string
    {
        return 'simulated';
    }

    public function parseIncoming(array $payload): array
    {
        return [
            'caller' => $payload['caller'] ?? $payload['From'] ?? '',
            'to' => $payload['to'] ?? $payload['To'] ?? '',
            'provider_call_id' => $payload['call_id'] ?? null,
        ];
    }

    public function connectToAgent(LiveCall $call, TeamMember $agent): void
    {
        // Real drivers would dial the agent's phone here. Nothing to do.
    }

    public function hangup(LiveCall $call): void
    {
        // Nothing to tear down in simulation.
    }

    public function recordingUrl(LiveCall $call): ?string
    {
        return 'https://recordings.superfone.test/live-'
            . str_pad((string) $call->id, 6, '0', STR_PAD_LEFT) . '.mp3';
    }
}
