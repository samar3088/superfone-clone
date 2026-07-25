<?php

namespace Database\Seeders;

use App\Models\Lead;
use App\Models\User;
use App\Support\Roles;
use Illuminate\Database\Seeder;

/**
 * Demo leads so the notifications bell and leads table have something to show
 * before the Facebook integration is connected.
 */
class DemoLeadSeeder extends Seeder
{
    public function run(): void
    {
        $memberIds = User::role(Roles::MEMBER)->pluck('id');

        $leads = [
            ['Rohit Sharma', '9810022001', 'rohit.sharma@example.in', 'facebook', 'Monsoon Offer — Lead Form'],
            ['Anita Desai', '9820033002', 'anita.desai@example.in', 'facebook', 'Monsoon Offer — Lead Form'],
            ['Vikram Rao', '9830044003', null, 'facebook', 'Festive Campaign 2026'],
            ['Priya Nair', '9840055004', 'priya.nair@example.in', 'facebook', 'Festive Campaign 2026'],
            ['Sameer Khan', '9850066005', null, 'manual', null],
            ['Meera Joshi', '9860077006', 'meera.joshi@example.in', 'facebook', 'Retargeting — Warm'],
            ['Karan Mehta', '9870088007', null, 'manual', null],
            ['Deepa Pillai', '9880099008', 'deepa.pillai@example.in', 'facebook', 'Retargeting — Warm'],
        ];

        foreach ($leads as $index => [$name, $mobile, $email, $source, $campaign]) {
            Lead::updateOrCreate(
                ['mobile' => $mobile],
                [
                    'name' => $name,
                    'email' => $email,
                    'source' => $source,
                    'campaign' => $campaign,
                    'assigned_to' => $memberIds[$index % max(1, $memberIds->count())] ?? null,
                    // The four most recent stay unread so the bell shows a badge.
                    'viewed_at' => $index < 4 ? null : now()->subHours($index),
                    'created_at' => now()->subHours($index * 3),
                ]
            );
        }

        $this->command?->info('Demo leads seeded: '.count($leads).' (4 unread)');
    }
}
