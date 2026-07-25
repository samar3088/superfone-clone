<?php

namespace Database\Seeders;

use App\Support\Permissions;
use App\Support\Roles;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // Owner holds every permission, including ones added later.
        Role::findOrCreate(Roles::OWNER, 'web')
            ->syncPermissions(Permissions::ALL);

        // Members get an explicit, deliberately small grant list.
        Role::findOrCreate(Roles::MEMBER, 'web')
            ->syncPermissions(Permissions::MEMBER_GRANTS);

        $this->command?->info('Roles seeded: '.implode(', ', Roles::ALL));
    }
}
