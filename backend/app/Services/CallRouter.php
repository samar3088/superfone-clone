<?php

namespace App\Services;

use App\Models\CallLog;
use App\Models\Customer;
use App\Models\LiveCall;
use App\Models\TeamMember;
use App\Telephony\Telephony;

/**
 * Provider-independent call routing.
 *
 * Rule (per client): an incoming call rings the agent who last spoke with
 * that caller ("sticky agent"). If they don't pick up, the call opens to
 * every active member of the team.
 */
class CallRouter
{
    /** An incoming call has hit one of the org's virtual numbers. */
    public function handleIncoming(
        Customer $org,
        string $caller,
        string $virtualNumber,
        string $provider = 'simulated',
        ?string $providerCallId = null,
    ): LiveCall {
        // Settings → Call Settings → Sticky agent toggle controls routing.
        $stickyEnabled = \App\Models\OrgSetting::forOrg($org->id)->sticky_agent;
        $sticky = $stickyEnabled ? $this->stickyAgentFor($org, $caller) : null;

        return LiveCall::create([
            'provider' => $provider,
            'provider_call_id' => $providerCallId,
            'customer_id' => $org->id,
            'virtual_number' => $virtualNumber,
            'caller' => $caller,
            'sticky_agent_id' => $sticky?->id,
            'ring_all' => $sticky === null, // no history → ring everyone at once
            'status' => 'ringing',
            'started_at' => now(),
        ]);
    }

    /** The active member who most recently talked to this caller. */
    public function stickyAgentFor(Customer $org, string $caller): ?TeamMember
    {
        $lastCall = CallLog::where('customer_id', $org->id)
            ->where('caller', $caller)
            ->whereNotNull('agent_id')
            ->where('status', 'completed')
            ->latest('called_at')
            ->first();

        if (! $lastCall) {
            return null;
        }

        return TeamMember::where('id', $lastCall->agent_id)
            ->where('status', 'active')
            ->first();
    }

    /** An agent picks up. */
    public function answer(LiveCall $call, TeamMember $agent): LiveCall
    {
        abort_unless($call->status === 'ringing', 422, 'Call is not ringing.');

        // Before escalation only the sticky agent may pick up.
        if (! $call->ring_all && $call->sticky_agent_id && $agent->id !== $call->sticky_agent_id) {
            abort(422, 'Call is still ringing its assigned agent.');
        }

        Telephony::driver($call->provider)->connectToAgent($call, $agent);

        $call->update([
            'status' => 'in_progress',
            'answered_by' => $agent->id,
            'answered_at' => now(),
        ]);

        return $call;
    }

    /** Sticky agent didn't pick — open the call to the whole team. */
    public function escalate(LiveCall $call): LiveCall
    {
        abort_unless($call->status === 'ringing', 422, 'Call is not ringing.');
        $call->update(['ring_all' => true]);

        return $call;
    }

    /** Call ends (answered → completed, still ringing → missed). */
    public function hangup(LiveCall $call): LiveCall
    {
        abort_if(in_array($call->status, ['completed', 'missed'], true), 422, 'Call already ended.');

        $driver = Telephony::driver($call->provider);
        $driver->hangup($call);

        $answered = $call->status === 'in_progress';
        $duration = $answered ? max(1, now()->diffInSeconds($call->answered_at)) : 0;

        $call->update([
            'status' => $answered ? 'completed' : 'missed',
            'ended_at' => now(),
        ]);

        // Write call history — this is what future sticky lookups use.
        CallLog::create([
            'customer_id' => $call->customer_id,
            'agent_id' => $answered ? $call->answered_by : null,
            'virtual_number' => $call->virtual_number,
            'caller' => $call->caller,
            'direction' => 'inbound',
            'duration_sec' => $duration,
            'recording_url' => $answered ? $driver->recordingUrl($call) : null,
            'status' => $answered ? 'completed' : 'missed',
            'called_at' => $call->started_at,
        ]);

        return $call;
    }
}
