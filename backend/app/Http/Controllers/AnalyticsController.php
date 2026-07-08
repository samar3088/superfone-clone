<?php

namespace App\Http\Controllers;

use App\Models\CallLog;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\Payment;
use App\Models\TeamMember;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AnalyticsController extends Controller
{
    public function index()
    {
        return response()->json([
            'revenue_trend' => $this->revenueTrend(),
            'calls_by_direction' => $this->callsByDirection(),
            'lead_funnel' => $this->leadFunnel(),
            'customers_by_status' => $this->customersByStatus(),
            'top_agents' => $this->topAgents(),
            'totals' => $this->totals(),
        ]);
    }

    /** Last 6 months of collected revenue. */
    private function revenueTrend(): array
    {
        $rows = Payment::where('status', 'paid')
            ->where('paid_at', '>=', now()->startOfMonth()->subMonths(5))
            ->selectRaw("DATE_FORMAT(paid_at, '%Y-%m') as ym, SUM(amount) as total")
            ->groupBy('ym')
            ->pluck('total', 'ym');

        $out = [];
        for ($i = 5; $i >= 0; $i--) {
            $month = now()->startOfMonth()->subMonths($i);
            $key = $month->format('Y-m');
            $out[] = [
                'label' => $month->format('M'),
                'value' => (float) ($rows[$key] ?? 0),
            ];
        }

        return $out;
    }

    private function callsByDirection(): array
    {
        $counts = CallLog::select('direction', DB::raw('COUNT(*) as total'))
            ->groupBy('direction')
            ->pluck('total', 'direction');

        return [
            'inbound' => (int) ($counts['inbound'] ?? 0),
            'outbound' => (int) ($counts['outbound'] ?? 0),
            'missed' => (int) ($counts['missed'] ?? 0),
        ];
    }

    private function leadFunnel(): array
    {
        $counts = Lead::select('stage', DB::raw('COUNT(*) as total'))
            ->groupBy('stage')
            ->pluck('total', 'stage');

        $stages = ['new', 'contacted', 'qualified', 'won', 'lost'];
        $out = [];
        foreach ($stages as $stage) {
            $out[$stage] = (int) ($counts[$stage] ?? 0);
        }

        return $out;
    }

    private function customersByStatus(): array
    {
        $counts = Customer::select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        return [
            'active' => (int) ($counts['active'] ?? 0),
            'trial' => (int) ($counts['trial'] ?? 0),
            'suspended' => (int) ($counts['suspended'] ?? 0),
            'churned' => (int) ($counts['churned'] ?? 0),
        ];
    }

    private function topAgents()
    {
        return TeamMember::where('role', 'agent')
            ->withCount('handledCalls')
            ->orderByDesc('handled_calls_count')
            ->limit(6)
            ->get(['id', 'name'])
            ->map(fn ($m) => ['name' => $m->name, 'calls' => $m->handled_calls_count]);
    }

    private function totals(): array
    {
        $wonValue = (float) Lead::where('stage', 'won')->sum('value');
        $totalLeads = Lead::count();
        $wonLeads = Lead::where('stage', 'won')->count();

        return [
            'collected' => (float) Payment::where('status', 'paid')->sum('amount'),
            'outstanding' => (float) Payment::where('status', 'pending')->sum('amount'),
            'won_value' => $wonValue,
            'conversion_rate' => $totalLeads > 0 ? round($wonLeads / $totalLeads * 100, 1) : 0,
        ];
    }
}
