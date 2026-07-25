<?php

namespace Database\Seeders;

use App\Models\User;
use App\Support\Roles;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class OwnerSeeder extends Seeder
{
    public function run(): void
    {
        $owner = User::withTrashed()->updateOrCreate(
            ['mobile' => '9999900001'],
            [
                'name' => 'System Owner',
                'email' => 'owner@superfone.test',
                'password' => Hash::make('Owner@123'),
                'is_active' => true,
                'mobile_verified_at' => now(),
                'email_verified_at' => now(),
                'deleted_at' => null,
            ]
        );

        $owner->syncRoles([Roles::OWNER]);

        $this->command?->warn('Owner login → mobile 9999900001 · password Owner@123');
    }
}
