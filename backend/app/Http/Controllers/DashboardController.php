<?php

namespace App\Http\Controllers;

use App\Models\AiAgent;
use App\Models\CallLog;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Reminder;
use App\Models\VirtualNumber;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        $activeCustomers = Customer::where('status', 'active')->count();

        // Monthly recurring revenue from active customers' plans.
        $mrr = Customer::where('customers.status', 'active')
            ->join('plans', 'customers.plan_id', '=', 'plans.id')
            ->sum('plans.price');

        // Calls in the last 7 days grouped by day for the chart.
        $callTrend = CallLog::where('called_at', '>=', now()->subDays(6)->startOfDay())
            ->selectRaw('DATE(called_at) as day, COUNT(*) as total')
            ->groupBy('day')
            ->orderBy('day')
            ->get();

        $trend = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i)->format('Y-m-d');
            $match = $callTrend->firstWhere('day', $date);
            $trend[] = [
                'date' => now()->subDays($i)->format('D'),
                'count' => $match ? (int) $match->total : 0,
            ];
        }

        $statusBreakdown = Customer::select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        return response()->json([
            'stats' => [
                'customers' => Customer::count(),
                'active_customers' => $activeCustomers,
                'numbers_assigned' => VirtualNumber::where('status', 'assigned')->count(),
                'numbers_available' => VirtualNumber::where('status', 'available')->count(),
                'calls_today' => CallLog::whereDate('called_at', today())->count(),
                'missed_today' => CallLog::whereDate('called_at', today())->where('status', 'missed')->count(),
                'mrr' => (float) $mrr,
                'plans' => Plan::count(),
                // Cross-module highlights
                'open_leads' => Lead::whereNotIn('stage', ['won', 'lost'])->count(),
                'pipeline_value' => (float) Lead::whereNotIn('stage', ['lost'])->sum('value'),
                'pending_reminders' => Reminder::where('status', 'pending')->count(),
                'open_chats' => Conversation::where('status', 'open')->count(),
                'active_agents' => AiAgent::where('status', 'active')->count(),
                'collected_month' => (float) Payment::where('status', 'paid')
                    ->whereMonth('paid_at', now()->month)
                    ->whereYear('paid_at', now()->year)
                    ->sum('amount'),
            ],
            'call_trend' => $trend,
            'customer_status' => $statusBreakdown,
            'recent_calls' => CallLog::with('customer:id,business_name')
                ->latest('called_at')
                ->limit(6)
                ->get(),
            'recent_customers' => Customer::with('plan:id,name')
                ->latest()
                ->limit(5)
                ->get(),
        ]);
    }
}
