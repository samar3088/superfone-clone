<?php

namespace App\Telephony;

use App\Models\LiveCall;
use App\Models\TeamMember;

/**
 * Contract every telephony provider driver implements. The app's call
 * routing, live dashboard, and history only talk to this interface, so
 * swapping the simulated driver for Exotel/Twilio/Plivo later means
 * writing one driver class — nothing else changes.
 */
interface TelephonyProvider
{
    public function name(): string;

    /**
     * Normalize an incoming-call webhook payload to:
     * ['caller' => string, 'to' => string, 'provider_call_id' => ?string]
     */
    public function parseIncoming(array $payload): array;

    /** Bridge the live call to an agent (real providers dial the agent). */
    public function connectToAgent(LiveCall $call, TeamMember $agent): void;

    /** Terminate the call on the provider side. */
    public function hangup(LiveCall $call): void;

    /** URL of the call recording, once the call has ended. */
    public function recordingUrl(LiveCall $call): ?string;
}
