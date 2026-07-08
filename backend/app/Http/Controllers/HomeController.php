<?php

namespace App\Http\Controllers;

use App\Models\CallLog;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Owner's Home page: call/customer/staff insights for a date range,
 * plus the subscription overview for their orgs.
 */
class HomeController extends Controller
{
    public function index(Request $request)
    {
        $from = $request->query('from')
            ? Carbon::parse($request->query('from'))->startOfDay()
            : now()->startOfDay();
        $to = $request->query('to')
            ? Carbon::parse($request->query('to'))->endOfDay()
            : now()->endOfDay();

        $orgIds = Customer::where('user_id', $request->user()->id)->pluck('id');

        $calls = CallLog::whereIn('customer_id', $orgIds)
            ->whereBetween('called_at', [$from, $to])
            ->get();

        $outgoing = $calls->where('direction', 'outbound');
        $incoming = $calls->whereIn('direction', ['inbound', 'missed']);
        $connected = $outgoing->where('status', 'completed');
        $received = $incoming->where('status', 'completed');
        $missed = $calls->where('status', 'missed');

        // Customer insights: unique callers, and callers we never picked up for.
        $uniqueCallers = $calls->pluck('caller')->unique();
        $missedCustomers = $uniqueCallers->filter(function ($caller) use ($calls) {
            $theirCalls = $calls->where('caller', $caller);
            return $theirCalls->where('status', 'completed')->isEmpty();
        });

        // Staff level insights.
        $staff = CallLog::whereIn('customer_id', $orgIds)
            ->whereBetween('called_at', [$from, $to])
            ->whereNotNull('agent_id')
            ->with('agent:id,name')
            ->get()
            ->groupBy('agent_id')
            ->map(function ($agentCalls) {
                return [
                    'name' => $agentCalls->first()->agent?->name ?? '—',
                    'calls' => $agentCalls->count(),
                    'connected' => $agentCalls->where('status', 'completed')->count(),
                    'missed' => $agentCalls->where('status', 'missed')->count(),
                    'talk_time_sec' => (int) $agentCalls->sum('duration_sec'),
                ];
            })
            ->sortByDesc('calls')
            ->values();

        // Subscription overview across the owner's orgs.
        $orgs = Customer::whereIn('id', $orgIds)->get();

        return response()->json([
            'range' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'synced_at' => now()->toIso8601String(),
            'call_insights' => [
                'outgoing' => $outgoing->count(),
                'incoming' => $incoming->count(),
                'connected' => $connected->count(),
                'received' => $received->count(),
                'missed' => $missed->count(),
                'talk_time_sec' => (int) $calls->sum('duration_sec'),
                'avg_outgoing_sec' => $connected->count() ? (int) round($connected->avg('duration_sec')) : 0,
                'avg_incoming_sec' => $received->count() ? (int) round($received->avg('duration_sec')) : 0,
            ],
            'customer_insights' => [
                'unique_customers' => $uniqueCallers->count(),
                'missed_customers' => $missedCustomers->count(),
            ],
            'staff_insights' => $staff,
            'subscription' => [
                'teams' => $orgs->count(),
                'expiring_soon' => $orgs->filter(fn ($o) => $o->expires_at
                    && $o->expires_at->isFuture()
                    && $o->expires_at->lte(now()->addDays(30)))->count(),
                'expired' => $orgs->filter(fn ($o) => $o->expires_at && $o->expires_at->isPast())->count(),
                'on_trial' => $orgs->where('status', 'trial')->count(),
            ],
        ]);
    }
}
