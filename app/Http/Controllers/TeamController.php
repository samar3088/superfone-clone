<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Models\Team;
use App\Models\User;
use App\Support\Permissions;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The business and its plan. Not to be confused with Team Members, which is
 * the staff roster — this is the organisation those staff belong to.
 */
class TeamController extends Controller
{
    public function index(): Response
    {
        abort_unless(request()->user()?->can(Permissions::SETTINGS_VIEW), 403);

        /*
         | Counted live rather than stored. A cached figure that drifts from the
         | roster is worse than no figure, and both queries are trivial.
         */
        $staff = User::where('is_active', true)->count();
        $leads = Lead::count();

        return Inertia::render('teams/index', [
            'teams' => Team::orderBy('id')->get()->map(fn (Team $t) => [
                ...$t->toArray(),
                'has_number' => $t->hasNumber(),
                'staff_count' => $staff,
                'leads_count' => $leads,
            ]),
        ]);
    }
}
