<?php

namespace App\Http\Controllers;

use App\Services\Support\DataTableService;
use App\Support\Permissions;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Activitylog\Models\Activity;

class ActivityLogController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()?->can(Permissions::ACTIVITY_VIEW), 403);

        $activities = DataTableService::for(Activity::query()->with('causer:id,name'))
            ->select(['id', 'log_name', 'description', 'subject_type', 'subject_id', 'causer_id', 'properties', 'created_at'])
            ->searchable(['description', 'log_name'])
            ->sortable(['created_at', 'log_name'])
            ->filter('log_name', fn (Builder $q, $v) => $q->where('log_name', $v))
            ->defaultSort('id', 'desc')
            ->paginate($request);

        $activities->through(fn (Activity $a) => [
            'id' => $a->id,
            'log_name' => $a->log_name,
            'description' => $a->description,
            'subject' => $a->subject_type ? class_basename($a->subject_type).' #'.$a->subject_id : null,
            'causer' => $a->causer?->name ?? 'System',
            'changes' => $a->properties->toArray(),
            'created_at' => $a->created_at->toDayDateTimeString(),
            'ago' => $a->created_at->diffForHumans(),
        ]);

        return Inertia::render('activity/index', [
            'activities' => $activities,
            'filters' => $request->only(['search', 'log_name', 'sort', 'direction']),
            'logNames' => Activity::query()->distinct()->orderBy('log_name')->pluck('log_name'),
        ]);
    }
}
