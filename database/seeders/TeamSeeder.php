<?php

namespace Database\Seeders;

use App\Models\Team;
use Illuminate\Database\Seeder;

class TeamSeeder extends Seeder
{
    public function run(): void
    {
        /*
         | Seeded from the configured legal name so a fresh install comes up
         | carrying the business's own identity rather than a placeholder
         | someone has to remember to change.
         |
         | Keyed on the row, not the name: re-running after the configured name
         | changes must rename the organisation, never add a second one. The
         | number and plan limits are left alone — those are earned, not seeded.
         */
        $team = Team::firstOrNew(['id' => 1]);

        $team->name = config('company.legal_name');
        $team->status ??= 'active';
        $team->save();

        $this->command?->info('Team seeded: '.$team->name);
    }
}
