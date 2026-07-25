<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            PermissionSeeder::class,
            RoleSeeder::class,
            // Before the people: a user created with no organisation to join
            // would land teamless.
            TeamSeeder::class,
            OwnerSeeder::class,
            DemoMemberSeeder::class,
            CrmSettingsSeeder::class,
            DemoCrmDataSeeder::class,
        ]);
    }
}
