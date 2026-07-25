<?php

namespace Database\Seeders;

use App\Models\User;
use App\Support\Roles;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Demo staff so tables, filters and exports have realistic data to show.
 * Safe to skip in production: `php artisan db:seed --class=OwnerSeeder`.
 */
class DemoMemberSeeder extends Seeder
{
    public function run(): void
    {
        $members = [
            ['Aarav Gupta', 'aarav.gupta@superfone.test', '9810000101', true],
            ['Diya Reddy', 'diya.reddy@superfone.test', '9810000102', true],
            ['Ishaan Verma', 'ishaan.verma@superfone.test', '9810000103', true],
            ['Saanvi Iyer', 'saanvi.iyer@superfone.test', '9810000104', true],
            ['Kabir Singh', 'kabir.singh@superfone.test', '9810000105', false],
            ['Aadhya Menon', 'aadhya.menon@superfone.test', '9810000106', true],
            ['Reyansh Jain', 'reyansh.jain@superfone.test', '9810000107', true],
            ['Anaya Bose', 'anaya.bose@superfone.test', '9810000108', false],
            ['Vivaan Shah', 'vivaan.shah@superfone.test', '9810000109', true],
            ['Myra Kapoor', 'myra.kapoor@superfone.test', '9810000110', true],
        ];

        foreach ($members as [$name, $email, $mobile, $active]) {
            $user = User::withTrashed()->updateOrCreate(
                ['mobile' => $mobile],
                [
                    'name' => $name,
                    'email' => $email,
                    'password' => Hash::make('Member@123'),
                    'is_active' => $active,
                    'mobile_verified_at' => now(),
                    'deleted_at' => null,
                ]
            );

            $user->syncRoles([Roles::MEMBER]);
        }

        $this->command?->info('Demo members seeded: '.count($members));
    }
}
