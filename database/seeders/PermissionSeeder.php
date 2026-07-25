<?php

namespace Database\Seeders;

use App\Support\Permissions;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (Permissions::ALL as $name) {
            Permission::findOrCreate($name, 'web');
        }

        $this->command?->info('Permissions seeded: '.count(Permissions::ALL));
    }
}
