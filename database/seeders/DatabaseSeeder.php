<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /*
     | Order is not a preference here — each step needs the one above it to
     | already exist:
     |
     |   permissions → roles      a role is a bundle of permissions
     |   roles       → users      a user is created holding a role
     |   team        → users      a user with no organisation lands teamless
     |   CRM settings→ leads      a lead has to point at a lead stage
     |   customers   → leads      a lead belongs to a customer, not the reverse
     |   leads       → to-dos     a to-do hangs off a lead and inherits its owner
     |
     | Customers and leads are seeded together in DemoCrmDataSeeder, in that
     | order, because the lead is created from the customer it belongs to.
     */
    public function run(): void
    {
        $this->call([
            PermissionSeeder::class,
            RoleSeeder::class,
            TeamSeeder::class,
            OwnerSeeder::class,
            DemoMemberSeeder::class,
            CrmSettingsSeeder::class,
            // Integration, then customers, then their leads and calls.
            DemoCrmDataSeeder::class,
            DemoTodoSeeder::class,
        ]);
    }
}
