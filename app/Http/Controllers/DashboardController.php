<?php

namespace App\Http\Controllers;

use App\Models\Call;
use App\Models\Customer;
use App\Models\User;
use App\Services\Insights\InsightService;
use App\Services\Support\ExportService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DashboardController extends Controller
{
    public function __construct(
        private InsightService $insights,
        private ExportService $exports,
    ) {}

    public function index(Request $request): Response
    {
        [$from, $to] = $this->range($request);

        return Inertia::render('dashboard', [
            'range' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'syncedAt' => now()->format('d/m/y h:i A'),
            'callInsights' => $this->insights->callInsights($from, $to),
            'customerInsights' => $this->insights->customerInsights($from, $to),
            'staffInsights' => $this->insights->staffInsights($from, $to),
        ]);
    }

    /** Every call record in the range. */
    public function exportCalls(Request $request): StreamedResponse
    {
        [$from, $to] = $this->range($request);

        return $this->exports->streamCsv(
            $this->insights->callsInRange($from, $to),
            ['Date & time', 'Direction', 'Status', 'Caller number', 'Customer', 'Staff', 'Duration (sec)', 'Duration', 'Recording'],
            fn (Call $c) => [
                $c->started_at->toDateTimeString(),
                ucfirst($c->direction),
                ucfirst($c->status),
                $c->caller_number,
                $c->customer?->name ?? '',
                $c->staff?->name ?? '',
                $c->duration_sec,
                $this->insights->duration($c->duration_sec),
                $c->recording_url ?? '',
            ],
            $this->filename('call-records', $from, $to),
        );
    }

    /** Every customer active in the range, with their lead and call counts. */
    public function exportCustomers(Request $request): StreamedResponse
    {
        [$from, $to] = $this->range($request);

        $query = $this->insights->customersInRange($from, $to)
            ->withCount([
                'leads',
                'calls',
                'calls as connected_calls_count' => fn ($q) => $q->where('status', 'connected'),
            ]);

        return $this->exports->streamCsv(
            $query,
            ['Name', 'Mobile', 'Email', 'City', 'Total leads', 'Total calls', 'Connected calls', 'Last activity', 'Added on'],
            fn (Customer $c) => [
                $c->name,
                $c->mobile,
                $c->email ?? '',
                $c->city ?? '',
                $c->leads_count,
                $c->calls_count,
                $c->connected_calls_count,
                $c->last_activity_at?->toDateTimeString() ?? '',
                $c->created_at->toDateTimeString(),
            ],
            $this->filename('customers', $from, $to),
        );
    }

    /** Per-staff totals for the range. */
    public function exportStaff(Request $request): StreamedResponse
    {
        [$from, $to] = $this->range($request);

        return $this->exports->streamCsv(
            $this->insights->staffQuery($from, $to),
            ['Staff name', 'Team', 'Outgoing calls', 'Incoming calls', 'Connected calls', 'Received calls', 'Missed calls', 'Total talk time', 'Avg call duration'],
            function (User $u) {
                $row = $this->insights->staffRow($u);

                return [
                    $row['name'], $row['team'], $row['outgoing_calls'], $row['incoming_calls'],
                    $row['connected_calls'], $row['received_calls'], $row['missed_calls'],
                    $row['talk_time'], $row['avg_outgoing'],
                ];
            },
            $this->filename('staff-insights', $from, $to),
        );
    }

    /**
     * Resolve the requested window, defaulting to today. The end date is
     * pushed to end-of-day so a single-day range includes the whole day.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    private function range(Request $request): array
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $from = isset($validated['from'])
            ? Carbon::parse($validated['from'])->startOfDay()
            : now()->startOfDay();

        $to = isset($validated['to'])
            ? Carbon::parse($validated['to'])->endOfDay()
            : now()->endOfDay();

        return [$from, $to];
    }

    private function filename(string $prefix, Carbon $from, Carbon $to): string
    {
        return sprintf('%s_%s_to_%s.csv', $prefix, $from->format('Y-m-d'), $to->format('Y-m-d'));
    }
}
