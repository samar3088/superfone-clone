<?php

namespace App\Services\Insights;

use App\Models\Call;
use App\Models\Customer;
use App\Models\User;
use App\Support\Roles;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Dashboard metrics. Every figure is aggregated in SQL over an indexed date
 * range — no rows are pulled into PHP to be counted.
 */
class InsightService
{
    /** Headline call figures for the period. */
    public function callInsights(Carbon $from, Carbon $to): array
    {
        $row = Call::query()
            ->between($from, $to)
            ->selectRaw("
                SUM(direction = 'outgoing')                              AS outgoing,
                SUM(direction = 'incoming')                              AS incoming,
                SUM(status = 'connected')                                AS connected,
                SUM(direction = 'incoming' AND status = 'received')      AS received,
                SUM(status = 'missed')                                   AS missed,
                COALESCE(SUM(duration_sec), 0)                           AS talk_time,
                COALESCE(AVG(CASE WHEN direction = 'outgoing' AND duration_sec > 0
                                  THEN duration_sec END), 0)             AS avg_outgoing,
                COALESCE(AVG(CASE WHEN direction = 'incoming' AND duration_sec > 0
                                  THEN duration_sec END), 0)             AS avg_incoming
            ")
            ->first();

        return [
            'outgoing' => (int) $row->outgoing,
            'incoming' => (int) $row->incoming,
            'connected' => (int) $row->connected,
            'received' => (int) $row->received,
            'missed' => (int) $row->missed,
            'talk_time' => $this->duration((int) $row->talk_time),
            'avg_outgoing' => $this->duration((int) round((float) $row->avg_outgoing)),
            'avg_incoming' => $this->duration((int) round((float) $row->avg_incoming)),
        ];
    }

    /**
     * Customer figures. A customer is a person in the customers table; one
     * person may hold many leads across campaigns but is counted once.
     * "Missed" means we never actually connected with them in the period.
     */
    public function customerInsights(Carbon $from, Carbon $to): array
    {
        $unique = $this->customersInRange($from, $to)->count();

        $missed = $this->customersInRange($from, $to)
            ->whereDoesntHave('calls', fn (Builder $q) => $q
                ->whereBetween('started_at', [$from, $to])
                ->where('status', 'connected'))
            ->whereHas('calls', fn (Builder $q) => $q->whereBetween('started_at', [$from, $to]))
            ->count();

        return [
            'unique_customers' => $unique,
            'missed_customers' => $missed,
        ];
    }

    /** Per-staff breakdown, one row per team member. */
    public function staffInsights(Carbon $from, Carbon $to)
    {
        return $this->staffQuery($from, $to)->get()->map(fn (User $u) => $this->staffRow($u));
    }

    /** The same query for streaming exports. */
    public function staffQuery(Carbon $from, Carbon $to): Builder
    {
        return User::query()
            ->select(['id', 'name'])
            ->withSum(['calls as talk_time' => fn ($q) => $q->whereBetween('started_at', [$from, $to])], 'duration_sec')
            ->withCount([
                'calls as outgoing_calls' => fn ($q) => $q->whereBetween('started_at', [$from, $to])->where('direction', 'outgoing'),
                'calls as incoming_calls' => fn ($q) => $q->whereBetween('started_at', [$from, $to])->where('direction', 'incoming'),
                'calls as connected_calls' => fn ($q) => $q->whereBetween('started_at', [$from, $to])->where('status', 'connected'),
                'calls as received_calls' => fn ($q) => $q->whereBetween('started_at', [$from, $to])->where('status', 'received'),
                'calls as missed_calls' => fn ($q) => $q->whereBetween('started_at', [$from, $to])->where('status', 'missed'),
            ])
            ->orderBy('name');
    }

    public function staffRow(User $user): array
    {
        $outgoing = (int) ($user->outgoing_calls ?? 0);
        $incoming = (int) ($user->incoming_calls ?? 0);
        $talk = (int) ($user->talk_time ?? 0);

        return [
            'id' => $user->id,
            'name' => $user->name,
            'team' => config('company.name'),
            'outgoing_calls' => $outgoing,
            'incoming_calls' => $incoming,
            'connected_calls' => (int) ($user->connected_calls ?? 0),
            'received_calls' => (int) ($user->received_calls ?? 0),
            'missed_calls' => (int) ($user->missed_calls ?? 0),
            'talk_time' => $this->duration($talk),
            'avg_outgoing' => $this->duration($outgoing > 0 ? (int) round($talk / max($outgoing + $incoming, 1)) : 0),
        ];
    }

    /** Customers active in the period, for the table and the export. */
    public function customersInRange(Carbon $from, Carbon $to): Builder
    {
        return Customer::query()
            ->active()
            ->where(function (Builder $q) use ($from, $to) {
                $q->whereBetween('created_at', [$from, $to])
                    ->orWhereHas('calls', fn (Builder $c) => $c->whereBetween('started_at', [$from, $to]))
                    ->orWhereHas('leads', fn (Builder $l) => $l->whereBetween('created_at', [$from, $to]));
            });
    }

    /** Call records in the period, for the export. */
    public function callsInRange(Carbon $from, Carbon $to): Builder
    {
        return Call::query()
            ->between($from, $to)
            ->with(['staff:id,name', 'customer:id,name'])
            ->orderBy('id');
    }

    /** Seconds → "1h 04m 12s" / "4m 12s" / "12s". */
    public function duration(int $seconds): string
    {
        if ($seconds <= 0) {
            return '0s';
        }

        $h = intdiv($seconds, 3600);
        $m = intdiv($seconds % 3600, 60);
        $s = $seconds % 60;

        return match (true) {
            $h > 0 => sprintf('%dh %02dm %02ds', $h, $m, $s),
            $m > 0 => sprintf('%dm %02ds', $m, $s),
            default => $s.'s',
        };
    }
}
