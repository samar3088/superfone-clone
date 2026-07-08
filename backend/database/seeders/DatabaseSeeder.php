<?php

namespace Database\Seeders;

use App\Models\Addon;
use App\Models\CallLog;
use App\Models\Customer;
use App\Models\Integration;
use App\Models\Lead;
use App\Models\LeadGroup;
use App\Models\LeadStage;
use App\Models\OrgSetting;
use App\Models\Plan;
use App\Models\RingOrder;
use App\Models\Tag;
use App\Models\TeamMember;
use App\Models\User;
use App\Models\VirtualNumber;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // ── Owner account (the logged-in business owner) ──
        $owner = User::updateOrCreate(
            ['email' => 'admin@superfone.test'],
            [
                'name' => 'shank',
                'password' => Hash::make('admin123'),
                'role' => 'owner',
                'phone' => '6360200382',
                'wallet_balance' => 998.80,
            ]
        );

        // ── Plans ──
        $plans = [
            ['name' => 'Starter', 'price' => 299, 'validity_days' => 30, 'users_included' => 1, 'features' => 'Single number, call recording'],
            ['name' => 'Growth', 'price' => 799, 'validity_days' => 30, 'users_included' => 5, 'features' => 'IVR, 5 users, analytics'],
            ['name' => 'Pro', 'price' => 1999, 'validity_days' => 30, 'users_included' => 15, 'features' => 'Multi-number, CRM sync, priority support'],
        ];
        foreach ($plans as $p) {
            Plan::updateOrCreate(['name' => $p['name']], $p);
        }
        $planByName = Plan::pluck('id', 'name');

        // ── The owner's organizations (Teams) ──
        $orgs = [
            [
                'business_name' => 'VARIETY VINTAGE TECHNOLOGIES PRIVATE LIMITED',
                'email' => 'shank@varietyvintage.in',
                'city' => 'Bengaluru',
                'plan' => 'Pro',
                'status' => 'active',
                'staff_limit' => 10,
                'leads_limit' => 10000,
                'expires_at' => now()->addDays(45),
            ],
            [
                'business_name' => 'Sharma Electronics',
                'email' => 'shank@sharmaelec.in',
                'city' => 'Delhi',
                'plan' => 'Growth',
                'status' => 'trial',
                'staff_limit' => 5,
                'leads_limit' => 5000,
                'expires_at' => now()->addDays(12), // shows under "Expiring soon"
            ],
        ];
        foreach ($orgs as $o) {
            Customer::updateOrCreate(
                ['email' => $o['email']],
                [
                    'user_id' => $owner->id,
                    'business_name' => $o['business_name'],
                    'owner_name' => 'shank',
                    'phone' => '6360200382',
                    'city' => $o['city'],
                    'plan_id' => $planByName[$o['plan']],
                    'status' => $o['status'],
                    'staff_limit' => $o['staff_limit'],
                    'leads_limit' => $o['leads_limit'],
                    'expires_at' => $o['expires_at'],
                ]
            );
        }
        $customerIds = Customer::orderBy('id')->pluck('id')->all();

        // ── Virtual numbers (each org's primary number) ──
        $primaryNumbers = ['+919403890373', '+919403890374'];
        foreach ($primaryNumbers as $i => $number) {
            VirtualNumber::updateOrCreate(
                ['number' => $number],
                [
                    'customer_id' => $customerIds[$i],
                    'type' => 'mobile',
                    'status' => 'assigned',
                    'assigned_at' => now(),
                ]
            );
        }

        // ── Team members (Team Members page; also staff insights) ──
        if (TeamMember::count() === 0) {
            $staff = [
                // [name, phone, role, org index]
                ['shank', '6360200382', 'owner', 0],
                ['Manoj Kumar', '9538342225', 'owner', 0],
                ['Afsana B2B', '9035503877', 'member', 0],
                ['Kavitha B2B', '9035505441', 'member', 0],
                ['Esha B2C', '9035504901', 'member', 0],
                ['Dilip New', '9113236426', 'member', 0],
                ['Yash Pawar', '9035504112', 'member', 0],
                ['Ravi Teja', '9035504228', 'member', 0],
                ['Rohit Sharma', '9810012345', 'owner', 1],
                ['Sneha Kulkarni', '9822013456', 'member', 1],
                ['Amit Patel', '9822014567', 'member', 1],
            ];
            foreach ($staff as $s) {
                TeamMember::create([
                    'customer_id' => $customerIds[$s[3]],
                    'name' => $s[0],
                    'email' => strtolower(str_replace(' ', '.', $s[0])) . '@team.in',
                    'phone' => $s[1],
                    'role' => $s[2],
                    'status' => 'active',
                ]);
            }
        }

        // ── Call logs (Home → Call/Customer/Staff insights) ──
        if (CallLog::count() === 0) {
            $numbers = VirtualNumber::whereNotNull('customer_id')->get();
            $directions = ['inbound', 'outbound', 'missed'];
            $statuses = ['completed', 'completed', 'completed', 'missed', 'voicemail'];
            for ($i = 0; $i < 80; $i++) {
                $vn = $numbers[$i % $numbers->count()];
                $dir = $directions[$i % 3];
                $status = $dir === 'missed' ? 'missed' : $statuses[$i % count($statuses)];
                $agent = TeamMember::where('customer_id', $vn->customer_id)
                    ->where('role', 'member')
                    ->inRandomOrder()
                    ->first();
                CallLog::create([
                    'customer_id' => $vn->customer_id,
                    'agent_id' => $agent?->id,
                    'virtual_number' => $vn->number,
                    'caller' => '9' . str_pad((string) (700000000 + ($i * 13457) % 99999999), 9, '0', STR_PAD_LEFT),
                    'direction' => $dir,
                    'duration_sec' => $status === 'completed' ? (30 + ($i * 7) % 600) : 0,
                    'status' => $status,
                    'called_at' => now()->subHours($i % 48),
                ]);
            }
        }

        // ── Leads (Teams page "LEADS x/y" counter) ──
        if (Lead::count() === 0) {
            $sources = ['referral', 'website', 'ads', 'walkin', 'other'];
            $stages = ['new', 'contacted', 'qualified', 'won', 'lost'];
            for ($i = 0; $i < 14; $i++) {
                Lead::create([
                    'customer_id' => $customerIds[$i % count($customerIds)],
                    'name' => 'Lead ' . ($i + 1),
                    'phone' => '9' . str_pad((string) (500000000 + ($i * 1234567) % 99999999), 9, '0', STR_PAD_LEFT),
                    'source' => $sources[$i % count($sources)],
                    'stage' => $stages[$i % count($stages)],
                    'value' => (5 + ($i % 10)) * 1000,
                ]);
            }
        }

        // ── Settings: tags ──
        if (Tag::count() === 0) {
            $tags = [
                ['👑 VIP', '#a855f7'], ['New customer', '#3b82f6'], ['Call back', '#06b6d4'],
                ['😍 Interested', '#22c55e'], ['🚫 No response', '#ef4444'], ['🔥 Hot lead', '#f97316'],
                ['🐞 Issue', '#dc2626'], ['Already converted', '#d946ef'], ['Matrimony Call', '#8b5cf6'],
                ['✔️ Converted', '#7c3aed'], ['❄️ COLD', '#6366f1'], ['Demo', '#a855f7'],
                ['my operator', '#be185d'], ['renewal', '#eab308'], ['Busy', '#db2777'],
                ['missed call', '#64748b'], ['miss call', '#f43f5e'],
            ];
            foreach ($tags as $t) {
                Tag::create(['customer_id' => $customerIds[0], 'name' => $t[0], 'color' => $t[1]]);
            }
        }

        // ── Settings: lead stages + groups ──
        if (LeadStage::count() === 0) {
            $stages = [
                ['🆕 New Inquiry', 'INITIAL', 359], ['📄 Quotation Sent', 'NONE', 0],
                ['⏳ Awaiting Decision', 'NONE', 334], ['💲 Purchase Negotiation', 'NONE', 0],
                ['✅ Sale Closed', 'FINAL_POSITIVE', 2376], ['❌ Not Interested', 'FINAL_NEGATIVE', 83],
                ['🔮 Future Follow-up', 'NONE', 327], ['No Response', 'NONE', 1611], ['Follow up', 'NONE', 2361],
            ];
            foreach ($stages as $i => $s) {
                LeadStage::create([
                    'customer_id' => $customerIds[0],
                    'sequence' => $i + 1,
                    'name' => $s[0],
                    'type' => $s[1],
                    'contacts_attached' => $s[2],
                ]);
            }
        }
        if (LeadGroup::count() === 0) {
            LeadGroup::create(['customer_id' => $customerIds[0], 'name' => 'Customers', 'type' => 'DEFAULT']);
        }

        // ── Settings: per-org call settings + ringing order ──
        foreach ($customerIds as $cid) {
            OrgSetting::firstOrCreate(['customer_id' => $cid], [
                'sticky_agent' => true,
                'sticky_fallback' => 'ringing_order',
                'ivr_enabled' => true,
                'recording_enabled' => true,
            ]);
        }
        if (RingOrder::count() === 0) {
            $members = TeamMember::where('customer_id', $customerIds[0])->where('role', 'member')->pluck('id')->values();
            foreach ($members as $i => $memberId) {
                RingOrder::create([
                    'customer_id' => $customerIds[0],
                    'hours' => 'open',
                    'group_no' => $i < 3 ? 1 : 2,
                    'team_member_id' => $memberId,
                ]);
            }
            $ownerId = TeamMember::where('customer_id', $customerIds[0])->where('role', 'owner')->value('id');
            if ($ownerId) {
                RingOrder::create([
                    'customer_id' => $customerIds[0], 'hours' => 'closed', 'group_no' => 1, 'team_member_id' => $ownerId,
                ]);
            }
        }

        // ── Add-ons catalog (Teams → Addon Purchase modal) ──
        if (Addon::count() === 0) {
            $addons = [
                ['Extra team member', '🧑‍💼', 500, '/user /month', true],
                ['Automation (Pack of 5 automations)', '🤖', 250, '/month', true],
                ['Lead storage (Pack of 10K leads)', '🗂️', 300, '/month', true],
                ['Click to call Via API KEY', '📞', 750, '/month', false],
                ['WhatsApp AI Agent (Monthly)', '💬', 599, '/month', false],
                ['WhatsApp AI Agent (Quarterly)', '💬', 1797, '/month', false],
            ];
            foreach ($addons as $a) {
                Addon::create([
                    'name' => $a[0], 'icon' => $a[1], 'price' => $a[2],
                    'unit' => $a[3], 'quantity_based' => $a[4],
                ]);
            }
        }

        // ── Integrations (Facebook lead-form connections) ──
        if (Integration::count() === 0) {
            $integrations = [
                ['G Events', 'G Events Unlimited', 'G Events Unlimited', now()->subMonths(2)],
                ['Packages', 'Planawedding.in', 'Buy Lead & APP', now()->subMonths(4)],
                ['VVT1', 'Planawedding.in', 'Jan 29th CRM Lead form', now()->subMonths(5)],
            ];
            foreach ($integrations as $i) {
                Integration::create([
                    'customer_id' => $customerIds[0],
                    'provider' => 'facebook',
                    'name' => $i[0],
                    'page_name' => $i[1],
                    'form_name' => $i[2],
                    'connected_account' => 'Shank K Vasudev',
                    'status' => 'active',
                    'created_by' => 'shank',
                    'created_at' => $i[3],
                    'updated_at' => $i[3],
                ]);
            }
        }
    }
}
