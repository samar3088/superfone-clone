<?php

namespace App\Services\Team;

use App\Mail\MemberWelcomeMail;
use App\Models\Team;
use App\Models\User;
use App\Services\Support\DataTableService;
use App\Support\FilterList;
use App\Support\Roles;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class MemberService
{
    /** Server-side paginated list for the members table. */
    public function paginate(Request $request): LengthAwarePaginator
    {
        return $this->table($request)->paginate($request);
    }

    /** The same query, unpaginated, for streaming exports. */
    public function exportQuery(Request $request): Builder
    {
        return $this->table($request)->query($request)->with('team:id,name');
    }

    private function table(Request $request): DataTableService
    {
        return DataTableService::for(User::query()->with(['roles:id,name', 'team:id,name']))
            ->select(['id', 'team_id', 'name', 'email', 'mobile', 'is_active', 'last_login_at', 'created_at'])
            ->searchable(['name', 'email', 'mobile'])
            ->sortable(['name', 'email', 'mobile', 'created_at', 'last_login_at'])
            ->filter('status', fn (Builder $q, $v) => $q->where('is_active', $v === 'active'))
            ->filter('role', fn (Builder $q, $v) => $q->whereHas('roles', fn ($r) => $r->where('name', $v)))
            ->filter('team', fn (Builder $q, $v) => $q->whereIn('team_id', FilterList::ids($v)))
            ->defaultSort('id', 'desc');
    }

    /**
     * Create a member and email them their way in.
     *
     * Members normally sign in with a one-time code, but a temporary password
     * is issued too so the welcome email carries something they can act on
     * immediately. It is single-use in practice: `must_reset_password` forces
     * them to choose their own the first time they use it, which stops the
     * emailed one lingering in an inbox as a working credential.
     */
    public function create(array $data, ?User $actor = null): User
    {
        [$user, $temporaryPassword] = DB::transaction(function () use ($data) {
            $temporary = $this->temporaryPassword();

            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'mobile' => $data['mobile'],
                'is_active' => $data['is_active'] ?? true,
                // Falls back to the only organisation, so a member is never
                // orphaned just because the form did not offer a choice.
                'team_id' => $data['team_id'] ?? Team::query()->orderBy('id')->value('id'),
                'password' => Hash::make($data['password'] ?? $temporary),
                // An owner who typed a password has chosen it deliberately;
                // only our generated one has to be replaced.
                'must_reset_password' => empty($data['password']),
            ]);

            $user->syncRoles([$data['role'] ?? Roles::MEMBER]);

            return [$user, empty($data['password']) ? $temporary : $data['password']];
        });

        /*
         | Queued, and dispatched after the transaction commits — a mail worker
         | picking the job up mid-transaction would not find the user yet.
         */
        Mail::to($user->email)->queue(
            new MemberWelcomeMail($user, $temporaryPassword, $actor?->name ?? config('company.name'))
        );

        return $user;
    }

    /**
     * Readable but not guessable: mixed case and digits, no ambiguous
     * characters, since someone will be retyping this from an email.
     */
    private function temporaryPassword(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789';

        $password = '';
        for ($i = 0; $i < 12; $i++) {
            $password .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        // Password::min(8)->letters()->numbers() must hold for the reset that follows.
        return preg_match('/[a-zA-Z]/', $password) && preg_match('/\d/', $password)
            ? $password
            : $this->temporaryPassword();
    }

    public function update(User $member, array $data): User
    {
        return DB::transaction(function () use ($member, $data) {
            $member->fill([
                'name' => $data['name'],
                'email' => $data['email'],
                'mobile' => $data['mobile'],
                'is_active' => $data['is_active'] ?? $member->is_active,
                'team_id' => $data['team_id'] ?? $member->team_id,
            ]);

            if ($member->isDirty('mobile')) {
                $member->mobile_verified_at = null;
            }

            $member->save();

            if (! empty($data['role'])) {
                $this->assertNotLastOwner($member, $data['role']);
                $member->syncRoles([$data['role']]);
            }

            return $member;
        });
    }

    /**
     * Soft-delete a member. Owners are protected: the account type cannot be
     * removed, so administration can never be locked out.
     */
    public function delete(User $member, User $actor): void
    {
        if ($member->isOwner()) {
            throw ValidationException::withMessages([
                'member' => 'Owner accounts cannot be deleted.',
            ]);
        }

        if ($member->is($actor)) {
            throw ValidationException::withMessages([
                'member' => 'You cannot delete your own account.',
            ]);
        }

        $member->delete();

        activity('user')
            ->performedOn($member)
            ->causedBy($actor)
            ->log('Removed team member');
    }

    /** Prevent demoting the only remaining owner. */
    private function assertNotLastOwner(User $member, string $newRole): void
    {
        if (! $member->isOwner() || $newRole === Roles::OWNER) {
            return;
        }

        $otherOwners = User::role(Roles::OWNER)->whereKeyNot($member->id)->count();

        if ($otherOwners === 0) {
            throw ValidationException::withMessages([
                'role' => 'This is the only owner — promote someone else first.',
            ]);
        }
    }
}
