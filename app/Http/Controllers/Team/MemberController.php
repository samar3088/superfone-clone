<?php

namespace App\Http\Controllers\Team;

use App\Http\Controllers\Controller;
use App\Http\Requests\Team\StoreMemberRequest;
use App\Http\Requests\Team\UpdateMemberRequest;
use App\Models\User;
use App\Services\Support\ExportService;
use App\Services\Team\MemberService;
use App\Support\Permissions;
use App\Support\Roles;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MemberController extends Controller
{
    public function __construct(
        private MemberService $members,
        private ExportService $exports,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorizeAbility(Permissions::MEMBER_VIEW);

        return Inertia::render('team/index', [
            'members' => $this->members->paginate($request),
            'filters' => $request->only(['search', 'status', 'role', 'sort', 'direction', 'per_page']),
            'roles' => Roles::ALL,
        ]);
    }

    public function store(StoreMemberRequest $request): RedirectResponse
    {
        $member = $this->members->create($request->validated(), $request->user());

        return back()->with(
            'success',
            "{$member->name} has been added. Their sign-in details are on the way to {$member->email}."
        );
    }

    public function update(UpdateMemberRequest $request, User $member): RedirectResponse
    {
        $this->members->update($member, $request->validated());

        return back()->with('success', "{$member->name}'s details have been updated.");
    }

    public function destroy(Request $request, User $member): RedirectResponse
    {
        $this->authorizeAbility(Permissions::MEMBER_DELETE);

        $name = $member->name;
        $this->members->delete($member, $request->user());

        return back()->with('success', "{$name} has been removed.");
    }

    /** Streamed CSV of exactly the rows the current filters produce. */
    public function export(Request $request): StreamedResponse
    {
        $this->authorizeAbility(Permissions::MEMBER_EXPORT);

        return $this->exports->streamCsv(
            $this->members->exportQuery($request),
            ['Name', 'Email', 'Mobile', 'Role', 'Status', 'Last login', 'Added on'],
            fn (User $u) => [
                $u->name,
                $u->email,
                $u->mobile,
                $u->roles->pluck('name')->implode(', '),
                $u->is_active ? 'Active' : 'Inactive',
                $u->last_login_at?->toDateTimeString() ?? '',
                $u->created_at->toDateTimeString(),
            ],
            'team-members-'.now()->format('Y-m-d-Hi').'.csv',
        );
    }

    private function authorizeAbility(string $permission): void
    {
        abort_unless(request()->user()?->can($permission), 403);
    }
}
