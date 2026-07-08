<?php

namespace Database\Seeders;

use App\Models\Addon;
use App\Models\AiAgent;
use App\Models\CallerTune;
use App\Models\CallLog;
use App\Models\IvrFlow;
use App\Models\Campaign;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Integration;
use App\Models\MessageTemplate;
use App\Models\Lead;
use App\Models\LeadGroup;
use App\Models\LeadStage;
use App\Models\OrgSetting;
use App\Models\Payment;
use App\Models\RingOrder;
use App\Models\Tag;
use App\Models\Plan;
use App\Models\Reminder;
use App\Models\TeamMember;
use App\Models\User;
use App\Models\VirtualNumber;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // --- Owner account (the logged-in business owner) ---
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

        // --- Plans ---
        $plans = [
            ['name' => 'Starter', 'price' => 299, 'validity_days' => 30, 'users_included' => 1, 'features' => 'Single number, call recording'],
            ['name' => 'Growth', 'price' => 799, 'validity_days' => 30, 'users_included' => 5, 'features' => 'IVR, 5 users, analytics'],
            ['name' => 'Pro', 'price' => 1999, 'validity_days' => 30, 'users_included' => 15, 'features' => 'Multi-number, CRM sync, priority support'],
        ];
        foreach ($plans as $p) {
            Plan::updateOrCreate(['name' => $p['name']], $p);
        }
        $planIds = Plan::pluck('id')->all();

        // --- The owner's organizations (Teams) ---
        $orgs = [
            [
                'business_name' => 'VARIETY VINTAGE TECHNOLOGIES PRIVATE LIMITED',
                'owner_name' => 'shank',
                'email' => 'shank@varietyvintage.in',
                'phone' => '6360200382',
                'city' => 'Bengaluru',
                'plan' => 'Pro',
                'status' => 'active',
                'staff_limit' => 10,
                'leads_limit' => 10000,
                'expires_at' => now()->addDays(45),
            ],
            [
                'business_name' => 'Sharma Electronics',
                'owner_name' => 'shank',
                'email' => 'shank@sharmaelec.in',
                'phone' => '6360200382',
                'city' => 'Delhi',
                'plan' => 'Growth',
                'status' => 'trial',
                'staff_limit' => 5,
                'leads_limit' => 5000,
                'expires_at' => now()->addDays(12), // shows under "Expiring soon"
            ],
        ];
        $planByName = Plan::pluck('id', 'name');
        foreach ($orgs as $o) {
            Customer::updateOrCreate(
                ['email' => $o['email']],
                [
                    'user_id' => $owner->id,
                    'business_name' => $o['business_name'],
                    'owner_name' => $o['owner_name'],
                    'phone' => $o['phone'],
                    'city' => $o['city'],
                    'plan_id' => $planByName[$o['plan']] ?? $planIds[0],
                    'status' => $o['status'],
                    'staff_limit' => $o['staff_limit'],
                    'leads_limit' => $o['leads_limit'],
                    'expires_at' => $o['expires_at'],
                ]
            );
        }
        $customerIds = Customer::pluck('id')->all();

        // --- Virtual numbers (each org's primary number + spares) ---
        $primaryNumbers = ['+919403890373', '+919403890374'];
        foreach ($primaryNumbers as $i => $number) {
            if (! isset($customerIds[$i])) {
                break;
            }
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
        for ($i = 0; $i < 4; $i++) {
            VirtualNumber::updateOrCreate(
                ['number' => '1800' . str_pad((string) (200100 + $i), 6, '0', STR_PAD_LEFT)],
                ['customer_id' => null, 'type' => 'tollfree', 'status' => 'available', 'assigned_at' => null]
            );
        }

        // --- Call logs ---
        if (CallLog::count() === 0) {
            $assignedNumbers = VirtualNumber::whereNotNull('customer_id')->get();
            $directions = ['inbound', 'outbound', 'missed'];
            $statuses = ['completed', 'completed', 'completed', 'missed', 'voicemail'];
            for ($i = 0; $i < 80; $i++) {
                $vn = $assignedNumbers[$i % max(1, $assignedNumbers->count())];
                $dir = $directions[$i % 3];
                $status = $dir === 'missed' ? 'missed' : $statuses[$i % count($statuses)];
                $dur = $status === 'completed' ? (30 + ($i * 7) % 600) : 0;
                CallLog::create([
                    'customer_id' => $vn->customer_id,
                    'virtual_number' => $vn->number,
                    'caller' => '9' . str_pad((string) (700000000 + ($i * 13457) % 99999999), 9, '0', STR_PAD_LEFT),
                    'direction' => $dir,
                    'duration_sec' => $dur,
                    'status' => $status,
                    'called_at' => now()->subHours($i),
                ]);
            }
        }

        // --- Contacts ---
        if (Contact::count() === 0) {
            $tags = ['customer', 'prospect', 'vendor', 'other'];
            $names = [
                'Aarav Gupta', 'Diya Reddy', 'Ishaan Verma', 'Saanvi Iyer', 'Kabir Singh',
                'Aadhya Menon', 'Reyansh Jain', 'Anaya Bose', 'Vivaan Shah', 'Myra Kapoor',
                'Arjun Pillai', 'Kiara Nair', 'Advait Rao', 'Zara Khan', 'Ayaan Mehta',
                'Navya Desai',
            ];
            foreach ($names as $i => $name) {
                Contact::create([
                    'customer_id' => $customerIds[$i % count($customerIds)],
                    'name' => $name,
                    'phone' => '9' . str_pad((string) (600000000 + ($i * 7654321) % 99999999), 9, '0', STR_PAD_LEFT),
                    'email' => strtolower(str_replace(' ', '.', $name)) . '@example.in',
                    'company' => $i % 3 === 0 ? explode(' ', $name)[1] . ' Traders' : null,
                    'tag' => $tags[$i % count($tags)],
                    'notes' => $i % 4 === 0 ? 'Interested in the Growth plan. Follow up next week.' : null,
                    'last_contacted_at' => now()->subDays($i),
                ]);
            }
        }

        // --- Leads ---
        if (Lead::count() === 0) {
            $sources = ['referral', 'website', 'ads', 'walkin', 'other'];
            $stages = ['new', 'new', 'contacted', 'contacted', 'qualified', 'won', 'lost'];
            $owners = ['Rohit', 'Anita', 'Vikram', 'Priya', null];
            $leadNames = [
                'Tata Steel Dealer', 'Cafe Mocha', 'Pixel Studio', 'Fitline Gym', 'Royal Caterers',
                'MediCare Pharmacy', 'Quick Movers', 'Bloom Florist', 'Spice Route', 'AutoFix Garage',
                'EduSmart Coaching', 'Glow Salon', 'FreshMart', 'CloudNine Travels',
            ];
            foreach ($leadNames as $i => $name) {
                Lead::create([
                    'customer_id' => $customerIds[$i % count($customerIds)],
                    'name' => $name,
                    'phone' => '9' . str_pad((string) (500000000 + ($i * 1234567) % 99999999), 9, '0', STR_PAD_LEFT),
                    'email' => strtolower(str_replace(' ', '', $name)) . '@biz.in',
                    'source' => $sources[$i % count($sources)],
                    'stage' => $stages[$i % count($stages)],
                    'value' => (5 + ($i % 10)) * 1000,
                    'owner' => $owners[$i % count($owners)],
                    'notes' => $i % 3 === 0 ? 'Requested a callback regarding pricing.' : null,
                ]);
            }
        }

        // --- Reminders ---
        if (Reminder::count() === 0) {
            $contacts = Contact::all();
            $titles = [
                'Call back about renewal', 'Send pricing quote', 'Demo walkthrough',
                'Follow up on payment', 'Share onboarding docs', 'Confirm number porting',
                'Check satisfaction', 'Pitch Pro plan', 'Collect feedback', 'Schedule training',
            ];
            foreach ($titles as $i => $title) {
                $contact = $contacts[$i % max(1, $contacts->count())] ?? null;
                Reminder::create([
                    'customer_id' => $contact ? $contact->customer_id : $customerIds[0],
                    'contact_id' => $contact?->id,
                    'title' => $title,
                    'notes' => $i % 2 === 0 ? 'High priority.' : null,
                    'due_at' => now()->addDays($i - 4)->setTime(10 + ($i % 6), 0),
                    'status' => $i % 5 === 0 ? 'done' : 'pending',
                ]);
            }
        }

        // --- Team members (staff across the owner's orgs) ---
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
                if (! isset($customerIds[$s[3]])) {
                    continue;
                }
                TeamMember::create([
                    'customer_id' => $customerIds[$s[3]],
                    'name' => $s[0],
                    'email' => strtolower(str_replace(' ', '.', $s[0])) . '@team.in',
                    'phone' => $s[1],
                    'role' => $s[2],
                    'status' => 'active',
                ]);
            }

            // Attribute historical calls to members of the same org.
            foreach (CallLog::whereNull('agent_id')->get() as $call) {
                $agent = TeamMember::where('customer_id', $call->customer_id)
                    ->whereIn('role', ['member', 'admin'])
                    ->inRandomOrder()
                    ->first();
                if ($agent) {
                    $call->update(['agent_id' => $agent->id]);
                }
            }
        }

        // --- Payments / invoices (spread over the last 6 months) ---
        if (Payment::count() === 0) {
            $methods = ['card', 'upi', 'netbanking', 'wallet', 'cash'];
            $billable = Customer::with('plan')->whereNotNull('plan_id')->get();
            $seq = 1;
            // For each of the last 6 months, bill each active/trial customer for their plan.
            for ($m = 5; $m >= 0; $m--) {
                $month = now()->startOfMonth()->subMonths($m);
                foreach ($billable as $i => $customer) {
                    if (! $customer->plan) {
                        continue;
                    }
                    // Older months are paid; the current month has some pending/failed.
                    if ($m === 0) {
                        $status = match (($i + $seq) % 4) {
                            0 => 'pending',
                            1 => 'failed',
                            default => 'paid',
                        };
                    } else {
                        $status = ($i % 9 === 0) ? 'refunded' : 'paid';
                    }

                    $paidAt = in_array($status, ['paid', 'refunded'], true)
                        ? $month->copy()->setDay(min(5 + ($i % 20), 28))->setTime(12, 0)
                        : null;

                    Payment::create([
                        'invoice_no' => sprintf('INV-%s-%04d', $month->format('Y'), $seq++),
                        'customer_id' => $customer->id,
                        'plan_id' => $customer->plan_id,
                        'amount' => $customer->plan->price,
                        'method' => $methods[($i + $m) % count($methods)],
                        'status' => $status,
                        'paid_at' => $paidAt,
                        'created_at' => $month->copy()->setDay(1),
                        'updated_at' => $paidAt ?? $month->copy()->setDay(1),
                    ]);
                }
            }
        }

        // --- Message templates ---
        if (MessageTemplate::count() === 0) {
            $templates = [
                ['Welcome Message', 'utility', 'Hi {{1}}, welcome to {{2}}! We are glad to have you on board.', 'approved'],
                ['Payment Reminder', 'utility', 'Hi {{1}}, your invoice {{2}} of {{3}} is due. Please pay to avoid interruption.', 'approved'],
                ['Festive Offer', 'marketing', 'Hi {{1}}, enjoy 20% off on your next renewal this festive season. Offer valid till {{2}}.', 'approved'],
                ['OTP Verification', 'authentication', 'Your verification code is {{1}}. It is valid for 10 minutes.', 'approved'],
                ['Feedback Request', 'marketing', 'Hi {{1}}, how was your experience with us? Reply with a rating from 1-5.', 'pending'],
                ['Appointment Confirmation', 'utility', 'Hi {{1}}, your appointment on {{2}} is confirmed. See you soon!', 'rejected'],
            ];
            foreach ($templates as $t) {
                MessageTemplate::create([
                    'name' => $t[0], 'category' => $t[1], 'body' => $t[2], 'status' => $t[3],
                ]);
            }
        }

        // --- Conversations + messages ---
        if (Conversation::count() === 0) {
            $contacts = Contact::with('customer')->get();
            $inbound = [
                'Hi, is this number available for porting?',
                'What are your plan prices?',
                'I need help with call recording.',
                'Can you share the demo link?',
                'When will my number be activated?',
            ];
            $outbound = [
                'Hello! Yes, we can help you with that.',
                'Sure, our plans start at just ₹299/month.',
                'Call recording is enabled by default on all plans.',
                'Here is the demo link. Let me know if you have questions.',
                'Your number will be active within 24 hours.',
            ];
            foreach ($contacts->take(12) as $ci => $contact) {
                $conv = Conversation::create([
                    'customer_id' => $contact->customer_id,
                    'contact_id' => $contact->id,
                    'name' => $contact->name,
                    'phone' => $contact->phone,
                    'status' => $ci % 5 === 0 ? 'closed' : 'open',
                    'unread' => $ci % 3 === 0 ? rand(1, 3) : 0,
                    'last_message_at' => now()->subHours($ci * 2),
                ]);
                $turns = 2 + ($ci % 4);
                for ($t = 0; $t < $turns; $t++) {
                    $isInbound = $t % 2 === 0;
                    $conv->messages()->create([
                        'direction' => $isInbound ? 'inbound' : 'outbound',
                        'body' => $isInbound ? $inbound[$t % count($inbound)] : $outbound[$t % count($outbound)],
                        'status' => $isInbound ? 'delivered' : (['sent', 'delivered', 'read'][$t % 3]),
                        'sent_at' => now()->subHours($ci * 2)->addMinutes($t * 3),
                    ]);
                }
            }
        }

        // --- Campaigns ---
        if (Campaign::count() === 0) {
            $templateIds = MessageTemplate::where('status', 'approved')->pluck('id')->all();
            $segments = ['all', 'customer', 'prospect'];
            $campaignDefs = [
                ['Diwali Renewal Push', 'completed'],
                ['New Plan Announcement', 'completed'],
                ['Win-back Inactive', 'draft'],
                ['Monthly Newsletter', 'scheduled'],
                ['Feedback Drive', 'draft'],
            ];
            foreach ($campaignDefs as $i => $def) {
                $customer = $customerIds[$i % count($customerIds)];
                $segment = $segments[$i % count($segments)];
                $recipients = Contact::where('customer_id', $customer)
                    ->when($segment !== 'all', fn ($q) => $q->where('tag', $segment))
                    ->count();
                $isDone = $def[1] === 'completed';
                $delivered = $isDone ? (int) round($recipients * 0.94) : 0;
                Campaign::create([
                    'customer_id' => $customer,
                    'template_id' => $templateIds[$i % max(1, count($templateIds))] ?? null,
                    'name' => $def[0],
                    'segment' => $segment,
                    'status' => $def[1],
                    'recipients' => $recipients,
                    'sent' => $isDone ? $recipients : 0,
                    'delivered' => $delivered,
                    'read' => $isDone ? (int) round($delivered * 0.62) : 0,
                    'scheduled_at' => $def[1] === 'scheduled' ? now()->addDays(2) : ($isDone ? now()->subDays($i + 1) : null),
                ]);
            }
        }

        // --- AI Agents ---
        if (AiAgent::count() === 0) {
            $blueprints = [
                ['type' => 'receptionist', 'name' => 'AI Receptionist', 'channel' => 'call',
                    'persona' => 'Greets callers, answers FAQs, and routes calls to the right team member.'],
                ['type' => 'sales', 'name' => 'AI Sales Agent', 'channel' => 'both',
                    'persona' => 'Qualifies inbound leads, pitches plans, and books demos automatically.'],
                ['type' => 'whatsapp', 'name' => 'AI WhatsApp Agent', 'channel' => 'whatsapp',
                    'persona' => 'Replies to WhatsApp queries instantly with template-backed answers.'],
                ['type' => 'payment', 'name' => 'AI Payment Agent', 'channel' => 'both',
                    'persona' => 'Sends invoices, follows up on dues, and confirms payments.'],
                ['type' => 'manager', 'name' => 'AI Manager Agent', 'channel' => 'call',
                    'persona' => 'Summarises team calls and flags missed follow-ups to the owner.'],
            ];
            $statuses = ['active', 'active', 'paused', 'draft'];
            foreach ($customerIds as $ci => $customerId) {
                // Give each business one or two AI agents.
                $count = 1 + ($ci % 2);
                for ($a = 0; $a < $count; $a++) {
                    $bp = $blueprints[($ci + $a) % count($blueprints)];
                    $status = $statuses[($ci + $a) % count($statuses)];
                    AiAgent::create([
                        'customer_id' => $customerId,
                        'name' => $bp['name'],
                        'type' => $bp['type'],
                        'channel' => $bp['channel'],
                        'status' => $status,
                        'persona' => $bp['persona'],
                        'handled' => $status === 'active' ? 40 + (($ci * 17 + $a * 31) % 360) : 0,
                        'resolution_rate' => $status === 'active' ? 60 + (($ci * 7 + $a * 11) % 38) : 0,
                    ]);
                }
            }
        }

        // --- Call recordings (attach to completed calls) ---
        foreach (CallLog::where('status', 'completed')->whereNull('recording_url')->get() as $call) {
            $call->update([
                'recording_url' => 'https://recordings.superfone.test/rec-' . str_pad((string) $call->id, 6, '0', STR_PAD_LEFT) . '.mp3',
            ]);
        }

        // --- IVR flows ---
        if (IvrFlow::count() === 0) {
            foreach (Customer::whereIn('status', ['active', 'trial'])->take(5)->get() as $i => $customer) {
                $flow = IvrFlow::create([
                    'customer_id' => $customer->id,
                    'name' => 'Main Menu',
                    'greeting' => 'Thank you for calling ' . $customer->business_name . '. Please choose from the following options.',
                    'status' => $i % 2 === 0 ? 'active' : 'inactive',
                ]);
                $options = [
                    ['1', 'Sales', 'forward', 'Sales Team'],
                    ['2', 'Support', 'forward', 'Support Team'],
                    ['3', 'Billing', 'forward', 'Accounts'],
                    ['9', 'Leave a voicemail', 'voicemail', null],
                    ['0', 'Repeat menu', 'repeat', null],
                ];
                foreach ($options as $opt) {
                    $flow->options()->create([
                        'key_press' => $opt[0],
                        'label' => $opt[1],
                        'action' => $opt[2],
                        'destination' => $opt[3],
                    ]);
                }
            }
        }

        // --- Lead-source integrations (matches the real Integrations page) ---
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

        // --- Settings: tags, lead stages, lead groups, call settings ---
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

        foreach ($customerIds as $cid) {
            OrgSetting::firstOrCreate(['customer_id' => $cid], [
                'sticky_agent' => true,
                'sticky_fallback' => 'ringing_order',
                'ivr_enabled' => true,
                'recording_enabled' => true,
            ]);
        }

        if (RingOrder::count() === 0) {
            // Open hours: members ring in two groups; closed hours: owner only.
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

        // --- Add-ons catalog (matches the real Addon Purchase modal) ---
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

        // --- Caller tunes ---
        if (CallerTune::count() === 0) {
            $tunes = [
                ['Corporate Welcome', 'corporate', 30],
                ['Festive Bells', 'festive', 20],
                ['Default Ringback', 'default', 25],
                ['Custom Brand Jingle', 'custom', 35],
            ];
            foreach (Customer::whereIn('status', ['active', 'trial'])->take(6)->get() as $i => $customer) {
                $tune = $tunes[$i % count($tunes)];
                CallerTune::create([
                    'customer_id' => $customer->id,
                    'name' => $tune[0],
                    'genre' => $tune[1],
                    'duration_sec' => $tune[2],
                    'status' => $i % 2 === 0 ? 'active' : 'inactive',
                ]);
            }
        }
    }
}
