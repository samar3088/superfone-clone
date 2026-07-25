<?php

namespace App\Services\Account;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

class ProfileService
{
    /**
     * Update the signed-in user's own details.
     *
     * Mobile is the login identifier, so a change re-opens verification: the
     * user must confirm the new number with a code the next time they sign in.
     */
    public function update(User $user, array $data): User
    {
        $mobileChanged = isset($data['mobile']) && $data['mobile'] !== $user->mobile;
        $emailChanged = isset($data['email']) && $data['email'] !== $user->email;

        $user->fill([
            'name' => $data['name'],
            'email' => $data['email'],
            'mobile' => $data['mobile'],
        ]);

        if ($mobileChanged) {
            $user->mobile_verified_at = null;
        }

        if ($emailChanged) {
            $user->email_verified_at = null;
        }

        $user->save();

        return $user;
    }

    public function changePassword(User $user, string $newPassword): void
    {
        $user->forceFill(['password' => Hash::make($newPassword)])->save();

        activity('auth')->causedBy($user)->log('Changed password');
    }
}
