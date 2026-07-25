<?php

namespace App\Services\Team;

use App\Models\User;
use App\Services\Support\DataTableService;
use App\Support\Roles;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
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
        return $this->table($request)->query($request);
    }

    private function table(Request $request): DataTableService
    {
        return DataTableService::for(User::query()->with('roles:id,name'))
            ->select(['id', 'name', 'email', 'mobile', 'is_active', 'last_login_at', 'created_at'])
            ->searchable(['name', 'email', 'mobile'])
            ->sortable(['name', 'email', 'mobile', 'created_at', 'last_login_at'])
            ->filter('status', fn (Builder $q, $v) => $q->where('is_active', $v === 'active'))
            ->filter('role', fn (Builder $q, $v) => $q->whereHas('roles', fn ($r) => $r->where('name', $v)))
            ->defaultSort('id', 'desc');
    }

    public function create(array $data): User
    {
        return DB::transaction(function () use ($data) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'mobile' => $data['mobile'],
                'is_active' => $data['is_active'] ?? true,
                // Members sign in with an OTP; a password is optional and set
                // by them later from their own profile.
                'password' => ! empty($data['password']) ? Hash::make($data['password']) : null,
            ]);

            $user->syncRoles([$data['role'] ?? Roles::MEMBER]);

            return $user;
        });
    }

    public function update(User $member, array $data): User
    {
        return DB::transaction(function () use ($member, $data) {
            $member->fill([
                'name' => $data['name'],
                'email' => $data['email'],
                'mobile' => $data['mobile'],
                'is_active' => $data['is_active'] ?? $member->is_active,
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
