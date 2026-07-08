<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\LiveCall;
use App\Models\TeamMember;
use App\Models\VirtualNumber;
use App\Services\CallRouter;
use App\Telephony\Telephony;
use Illuminate\Http\Request;

class LiveCallController extends Controller
{
    public function __construct(private CallRouter $router)
    {
    }

    /** Live dashboard state: active calls + a few recent ones. */
    public function index(Request $request)
    {
        $orgIds = Customer::where('user_id', $request->user()->id)->pluck('id');

        $base = LiveCall::with([
            'customer:id,business_name',
            'stickyAgent:id,name',
            'answeredBy:id,name',
        ])->whereIn('customer_id', $orgIds);

        return response()->json([
            'active' => (clone $base)->whereIn('status', ['ringing', 'in_progress'])
                ->orderBy('started_at')->get(),
            'recent' => (clone $base)->whereIn('status', ['completed', 'missed'])
                ->latest('started_at')->limit(8)->get(),
            'sticky_ring_seconds' => config('telephony.sticky_ring_seconds'),
        ]);
    }

    /**
     * Incoming-call webhook — the single entry point every provider hits.
     * The "Simulate incoming call" button posts here too, proving the
     * plug-in point. Body (simulated): { to: virtual number, caller }.
     */
    public function webhook(Request $request, string $driver)
    {
        $provider = Telephony::driver($driver);
        $incoming = $provider->parseIncoming($request->all());

        abort_unless($incoming['caller'] && $incoming['to'], 422, 'caller and to are required.');

        // Which org owns the dialed virtual number?
        $number = VirtualNumber::where('number', $incoming['to'])->first();
        $org = $number?->customer ?? Customer::find($request->input('customer_id'));
        abort_unless($org, 404, 'No team owns this number.');

        $call = $this->router->handleIncoming(
            $org,
            $incoming['caller'],
            $incoming['to'],
            $provider->name(),
            $incoming['provider_call_id'],
        );

        return response()->json($call->load('stickyAgent:id,name'), 201);
    }

    public function answer(Request $request, LiveCall $liveCall)
    {
        $data = $request->validate(['team_member_id' => ['required', 'exists:team_members,id']]);
        $agent = TeamMember::findOrFail($data['team_member_id']);

        return $this->router->answer($liveCall, $agent)
            ->load('answeredBy:id,name', 'stickyAgent:id,name');
    }

    public function escalate(LiveCall $liveCall)
    {
        return $this->router->escalate($liveCall);
    }

    public function hangup(LiveCall $liveCall)
    {
        return $this->router->hangup($liveCall);
    }
}
