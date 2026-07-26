<?php

namespace Database\Seeders;

use App\Models\Call;
use App\Models\Customer;
use App\Models\Integration;
use App\Models\Lead;
use App\Models\LeadStage;
use App\Models\Team;
use App\Models\User;
use App\Support\Roles;
use Illuminate\Database\Seeder;

/**
 * Demo customers, leads and call records so the dashboard, insights and
 * exports have realistic data before VICI dial and Facebook are connected.
 */
class DemoCrmDataSeeder extends Seeder
{
    public function run(): void
    {
        $members = User::role(Roles::MEMBER)->where('is_active', true)->get();
        $stages = LeadStage::orderBy('sequence')->get();

        if ($members->isEmpty() || $stages->isEmpty()) {
            return;
        }

        $integration = Integration::updateOrCreate(
            ['provider' => 'facebook', 'external_form_id' => 'fm_1'],
            [
                'name' => 'G Events',
                'external_page_id' => 'pg_gevents',
                'page_name' => 'G Events Unlimited',
                'form_name' => 'G Events Unlimited',
                'connected_account' => 'Shank K Vasudev',
                'status' => 'active',
                'last_synced_at' => now(),
            ]
        );
        $integration->members()->syncWithoutDetaching($members->take(3)->pluck('id'));

        $people = [
            ['Rohit Sharma', '9810022001', 'rohit.sharma@example.in', 'Bengaluru'],
            ['Anita Desai', '9820033002', 'anita.desai@example.in', 'Mumbai'],
            ['Vikram Rao', '9830044003', null, 'Hyderabad'],
            ['Priya Nair', '9840055004', 'priya.nair@example.in', 'Kochi'],
            ['Sameer Khan', '9850066005', null, 'Pune'],
            ['Meera Joshi', '9860077006', 'meera.joshi@example.in', 'Jaipur'],
            ['Karan Mehta', '9870088007', null, 'Delhi'],
            ['Deepa Pillai', '9880099008', 'deepa.pillai@example.in', 'Chennai'],
        ];

        // Whose book these contacts sit in. Without it the To-Dos usage card
        // has only "No team" to report, which tells nobody anything.
        $teamId = Team::orderBy('id')->value('id');

        foreach ($people as $i => [$name, $mobile, $email, $city]) {
            $customer = Customer::updateOrCreate(
                ['mobile' => $mobile],
                [
                    'name' => $name, 'email' => $email, 'city' => $city,
                    'team_id' => $teamId,
                    'last_activity_at' => now()->subHours($i),
                ]
            );

            // The first two enquired twice — same customer, separate leads.
            $leadCount = $i < 2 ? 2 : 1;

            for ($n = 0; $n < $leadCount; $n++) {
                Lead::updateOrCreate(
                    [
                        'customer_id' => $customer->id,
                        'campaign' => $n === 0 ? 'G Events Unlimited' : 'Festive Campaign 2026',
                    ],
                    [
                        'name' => $name,
                        'mobile' => $mobile,
                        'email' => $email,
                        'source' => 'facebook',
                        'integration_id' => $integration->id,
                        'lead_stage_id' => $stages[($i + $n) % $stages->count()]->id,
                        'assigned_to' => $members[($i + $n) % $members->count()]->id,
                        'viewed_at' => $i < 4 ? null : now()->subHours($i),
                        'created_at' => now()->subHours($i * 3 + $n),
                    ]
                );
            }

            // A few call records per customer across the last few days.
            for ($c = 0; $c < 3; $c++) {
                $direction = $c % 2 === 0 ? 'incoming' : 'outgoing';

                // Derived from a different mix than the staff assignment below,
                // so outcomes don't correlate with who handled the call.
                $status = match (($i * 3 + $c * 7) % 5) {
                    0 => 'missed',
                    3 => $direction === 'incoming' ? 'received' : 'connected',
                    default => 'connected',
                };

                Call::updateOrCreate(
                    ['external_id' => "demo-{$customer->id}-{$c}"],
                    [
                        'customer_id' => $customer->id,
                        'user_id' => $members[($i + $c) % $members->count()]->id,
                        'caller_number' => $mobile,
                        'direction' => $direction,
                        'status' => $status,
                        'duration_sec' => $status === 'missed' ? 0 : 45 + (($i * 17 + $c * 31) % 400),
                        'started_at' => now()->subDays($c)->subMinutes($i * 13),
                    ]
                );
            }
        }

        $this->command?->info('Demo CRM data seeded: '.count($people).' customers with leads and calls');
    }
}
