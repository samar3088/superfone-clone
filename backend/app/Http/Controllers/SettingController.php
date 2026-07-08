<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\CustomField;
use App\Models\LeadGroup;
use App\Models\LeadStage;
use App\Models\OrgSetting;
use App\Models\RingOrder;
use App\Models\Tag;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    /** Lead-stage template sets shown in "Add from template". */
    private const STAGE_TEMPLATES = [
        'Healthcare and Fitness' => [
            ['🆕 Consultation Inquiry', 'INITIAL'],
            ['📅 Appointment Booked', 'NONE'],
            ['🧑‍⚕️ Consultation Completed', 'NONE'],
            ['🤸 Follow-up Session Scheduled', 'NONE'],
            ['💉 Tests/Treatment Initiated', 'FINAL_POSITIVE'],
            ['❌ Cancelled/No Show', 'FINAL_NEGATIVE'],
            ['🔄 Regular Check-up Reminder', 'NONE'],
            ['🔮 Future Health Plan', 'NONE'],
        ],
        'IT and Software' => [
            ['🆕 Product/Service Inquiry', 'INITIAL'],
            ['💻 Demo Scheduled', 'NONE'],
            ['📊 Proposal Sent', 'NONE'],
            ['⏳ Negotiating Terms', 'NONE'],
            ['🚀 Implementation Started', 'FINAL_POSITIVE'],
            ['📈 Ongoing Support', 'NONE'],
            ['❌ Not Proceeding', 'FINAL_NEGATIVE'],
            ['🔮 Future Tech Opportunities', 'NONE'],
        ],
        'Real Estate' => [
            ['🆕 Property Inquiry', 'INITIAL'],
            ['🏠 Site Visit Scheduled', 'NONE'],
            ['📄 Quotation Shared', 'NONE'],
            ['🤝 Negotiation', 'NONE'],
            ['✅ Booking Confirmed', 'FINAL_POSITIVE'],
            ['❌ Not Interested', 'FINAL_NEGATIVE'],
            ['🔮 Future Prospect', 'NONE'],
        ],
        'Education' => [
            ['🆕 Admission Inquiry', 'INITIAL'],
            ['🏫 Campus Tour Booked', 'NONE'],
            ['📝 Application Submitted', 'NONE'],
            ['✅ Enrolled', 'FINAL_POSITIVE'],
            ['❌ Dropped Out', 'FINAL_NEGATIVE'],
            ['🔮 Next Intake', 'NONE'],
        ],
        'Retail and E-commerce' => [
            ['🆕 Product Inquiry', 'INITIAL'],
            ['🛒 Added to Cart', 'NONE'],
            ['💳 Payment Pending', 'NONE'],
            ['✅ Order Placed', 'FINAL_POSITIVE'],
            ['❌ Abandoned', 'FINAL_NEGATIVE'],
            ['🔄 Repeat Customer', 'NONE'],
        ],
    ];

    /** Assert the org belongs to the logged-in owner and return it. */
    private function org(Request $request): Customer
    {
        $org = Customer::findOrFail($request->input('customer_id', $request->query('customer_id')));
        abort_unless($org->user_id === $request->user()->id, 403);

        return $org;
    }

    // ── Tags ─────────────────────────────────────────────

    public function tags(Request $request)
    {
        $org = $this->org($request);
        $tags = Tag::where('customer_id', $org->id)->orderBy('id')->get();

        return response()->json([
            'visible' => $tags->where('hidden', false)->values(),
            'hidden' => $tags->where('hidden', true)->values(),
        ]);
    }

    public function storeTag(Request $request)
    {
        $org = $this->org($request);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'color' => ['required', 'string', 'max:20'],
        ]);

        return response()->json(Tag::create($data + ['customer_id' => $org->id]), 201);
    }

    public function updateTag(Request $request, Tag $tag)
    {
        abort_unless(Customer::find($tag->customer_id)?->user_id === $request->user()->id, 403);
        $tag->update($request->validate([
            'name' => ['sometimes', 'string', 'max:60'],
            'color' => ['sometimes', 'string', 'max:20'],
            'hidden' => ['sometimes', 'boolean'],
        ]));

        return $tag;
    }

    public function destroyTag(Request $request, Tag $tag)
    {
        abort_unless(Customer::find($tag->customer_id)?->user_id === $request->user()->id, 403);
        $tag->delete();

        return response()->json(['message' => 'Tag deleted']);
    }

    // ── Lead stages ──────────────────────────────────────

    public function leadStages(Request $request)
    {
        $org = $this->org($request);

        return LeadStage::where('customer_id', $org->id)->orderBy('sequence')->get();
    }

    public function storeLeadStages(Request $request)
    {
        $org = $this->org($request);
        $data = $request->validate([
            'stages' => ['required', 'array', 'min:1'],
            'stages.*.name' => ['required', 'string', 'max:80'],
            'stages.*.type' => ['required', 'in:INITIAL,NONE,FINAL_POSITIVE,FINAL_NEGATIVE'],
        ]);

        $seq = (int) LeadStage::where('customer_id', $org->id)->max('sequence');
        $created = collect($data['stages'])->map(fn ($s) => LeadStage::create([
            'customer_id' => $org->id,
            'sequence' => ++$seq,
            'name' => $s['name'],
            'type' => $s['type'],
        ]));

        return response()->json($created, 201);
    }

    public function toggleLeadStage(Request $request, LeadStage $leadStage)
    {
        abort_unless(Customer::find($leadStage->customer_id)?->user_id === $request->user()->id, 403);
        $leadStage->update(['active' => ! $leadStage->active]);

        return $leadStage;
    }

    public function destroyLeadStage(Request $request, LeadStage $leadStage)
    {
        abort_unless(Customer::find($leadStage->customer_id)?->user_id === $request->user()->id, 403);
        $leadStage->delete();

        return response()->json(['message' => 'Lead stage deleted']);
    }

    public function stageTemplates(Request $request)
    {
        if ($category = $request->query('category')) {
            $rows = self::STAGE_TEMPLATES[$category] ?? [];

            return response()->json(
                collect($rows)->values()->map(fn ($r, $i) => [
                    'sequence' => $i + 1,
                    'name' => $r[0],
                    'type' => $r[1],
                ])
            );
        }

        return response()->json(array_keys(self::STAGE_TEMPLATES));
    }

    // ── Lead groups ──────────────────────────────────────

    public function leadGroups(Request $request)
    {
        $org = $this->org($request);

        return LeadGroup::where('customer_id', $org->id)->orderBy('id')->get();
    }

    public function storeLeadGroups(Request $request)
    {
        $org = $this->org($request);
        $data = $request->validate([
            'groups' => ['required', 'array', 'min:1'],
            'groups.*.name' => ['required', 'string', 'max:80'],
            'groups.*.type' => ['required', 'string', 'max:30'],
        ]);

        $created = collect($data['groups'])->map(fn ($g) => LeadGroup::create([
            'customer_id' => $org->id,
            'name' => $g['name'],
            'type' => strtoupper($g['type']),
        ]));

        return response()->json($created, 201);
    }

    public function toggleLeadGroup(Request $request, LeadGroup $leadGroup)
    {
        abort_unless(Customer::find($leadGroup->customer_id)?->user_id === $request->user()->id, 403);
        $leadGroup->update(['active' => ! $leadGroup->active]);

        return $leadGroup;
    }

    // ── Custom fields ────────────────────────────────────

    public function customFields(Request $request)
    {
        $org = $this->org($request);

        return CustomField::where('customer_id', $org->id)->orderBy('id')->get();
    }

    public function storeCustomField(Request $request)
    {
        $org = $this->org($request);
        $data = $request->validate([
            'label' => ['required', 'string', 'max:80'],
            'field_type' => ['required', 'in:text,number,date,dropdown,checkbox'],
        ]);

        return response()->json(CustomField::create($data + ['customer_id' => $org->id]), 201);
    }

    public function destroyCustomField(Request $request, CustomField $customField)
    {
        abort_unless(Customer::find($customField->customer_id)?->user_id === $request->user()->id, 403);
        $customField->delete();

        return response()->json(['message' => 'Custom field deleted']);
    }

    // ── Call settings + ringing order ────────────────────

    public function callSettings(Request $request)
    {
        $org = $this->org($request);
        $settings = OrgSetting::forOrg($org->id);
        $ringOrders = RingOrder::with('member:id,name')
            ->where('customer_id', $org->id)
            ->orderBy('group_no')
            ->get()
            ->groupBy('hours');

        return response()->json([
            'settings' => $settings,
            'ring_orders' => [
                'open' => $ringOrders->get('open', collect())->groupBy('group_no')->map->values(),
                'closed' => $ringOrders->get('closed', collect())->groupBy('group_no')->map->values(),
            ],
        ]);
    }

    public function updateCallSettings(Request $request)
    {
        $org = $this->org($request);
        $data = $request->validate([
            'sticky_agent' => ['sometimes', 'boolean'],
            'sticky_fallback' => ['sometimes', 'in:ringing_order,end_call'],
            'ivr_enabled' => ['sometimes', 'boolean'],
            'recording_enabled' => ['sometimes', 'boolean'],
        ]);

        $settings = OrgSetting::forOrg($org->id);
        $settings->update($data);

        return $settings;
    }

    public function saveRingOrder(Request $request)
    {
        $org = $this->org($request);
        $data = $request->validate([
            'hours' => ['required', 'in:open,closed'],
            'groups' => ['array'], // [[member_id, member_id], [member_id], …]
            'groups.*' => ['array'],
            'groups.*.*' => ['integer', 'exists:team_members,id'],
        ]);

        RingOrder::where('customer_id', $org->id)->where('hours', $data['hours'])->delete();
        foreach ($data['groups'] ?? [] as $i => $members) {
            foreach ($members as $memberId) {
                RingOrder::create([
                    'customer_id' => $org->id,
                    'hours' => $data['hours'],
                    'group_no' => $i + 1,
                    'team_member_id' => $memberId,
                ]);
            }
        }

        return response()->json(['message' => 'Ringing order saved']);
    }

    // ── Priority order ───────────────────────────────────

    private const DEFAULT_PRIORITY = [
        'lead' => [
            ['name' => 'Source', 'selected' => false],
            ['name' => 'Source Type', 'selected' => false],
            ['name' => 'Product Interested', 'selected' => false],
            ['name' => 'Deal Value', 'selected' => false],
            ['name' => 'Lead Group', 'selected' => false],
        ],
        'additional' => [
            ['name' => 'Business Name', 'selected' => false],
            ['name' => 'Additional Info', 'selected' => false],
            ['name' => 'City', 'selected' => false],
        ],
    ];

    public function priorityFields(Request $request)
    {
        $org = $this->org($request);
        $settings = OrgSetting::forOrg($org->id);

        return response()->json($settings->priority_fields ?: self::DEFAULT_PRIORITY);
    }

    public function savePriorityFields(Request $request)
    {
        $org = $this->org($request);
        $data = $request->validate([
            'lead' => ['required', 'array'],
            'additional' => ['required', 'array'],
        ]);

        $settings = OrgSetting::forOrg($org->id);
        $settings->update(['priority_fields' => ['lead' => $data['lead'], 'additional' => $data['additional']]]);

        return response()->json(['message' => 'Priority fields saved']);
    }
}
