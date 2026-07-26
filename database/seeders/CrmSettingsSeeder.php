<?php

namespace Database\Seeders;

use App\Models\FieldPriority;
use App\Models\LeadGroup;
use App\Models\LeadStage;
use App\Models\Tag;
use Illuminate\Database\Seeder;

class CrmSettingsSeeder extends Seeder
{
    public function run(): void
    {
        /*
         | Every tag the client already uses, taken from their own list.
         |
         | "missed call" and "miss call" are both theirs and both kept — one is
         | almost certainly a typo, but deleting a tag deletes it from whatever
         | it is attached to, so that is their call to make, not ours.
         */
        $tags = [
            ['VIP', '#7c3aed', '👑'],
            ['New customer', '#2563eb', null],
            ['Call back', '#0891b2', null],
            ['Interested', '#0f7a52', '😍'],
            ['No response', '#6b7280', '🚫'],
            ['Hot lead', '#c2410c', '🔥'],
            ['Issue', '#be3a2b', '🚨'],
            ['Follow-up', '#a855f7', null],
            ['Invalid', '#dc2626', null],
            ['Already converted', '#c026d3', null],
            ['Matrimony Call', '#7c3aed', null],
            ['Converted', '#6d28d9', '✔'],
            ['COLD', '#0891b2', '❄'],
            ['Demo', '#6366f1', null],
            ['my operator', '#db2777', null],
            ['renewal', '#ca8a04', null],
            ['Busy', '#be185d', null],
            ['missed call', '#6b7280', null],
            ['miss call', '#e11d48', null],
        ];
        foreach ($tags as [$name, $color, $emoji]) {
            Tag::updateOrCreate(['name' => $name], ['color' => $color, 'emoji' => $emoji]);
        }

        /*
         | The pipeline the client already works to, in their order.
         |
         | Only one stage may be INITIAL — it is where a new lead lands. The two
         | FINAL types are what the dashboard counts as won and lost; everything
         | else is a step along the way and stays NONE.
         */
        $stages = [
            ['New Inquiry', 'INITIAL', '🆕'],
            ['Quotation Sent', 'NONE', '📄'],
            ['Awaiting Decision', 'NONE', '⏳'],
            ['Purchase Negotiation', 'NONE', '💲'],
            ['Sale Closed', 'FINAL_POSITIVE', '✅'],
            ['Not Interested', 'FINAL_NEGATIVE', '❌'],
            ['Future Follow-up', 'NONE', '🔮'],
            ['No Response', 'NONE', null],
            ['Follow up', 'NONE', null],
            ['Registered', 'NONE', null],
            ['Already converted', 'NONE', null],
            ['Matrimony query', 'NONE', null],
            ['Cold', 'FINAL_NEGATIVE', null],
            ['No Requirement', 'NONE', null],
        ];

        foreach ($stages as $i => [$name, $type, $emoji]) {
            $stage = LeadStage::firstOrNew(['name' => $name]);

            $stage->fill(['type' => $type, 'emoji' => $emoji, 'sequence' => $i + 1]);

            // Only on creation: re-running the seeder must not switch a stage
            // back on that an owner has deliberately turned off.
            if (! $stage->exists) {
                $stage->is_active = true;
            }

            $stage->save();
        }

        LeadGroup::updateOrCreate(['name' => 'Customers'], ['type' => 'DEFAULT', 'is_active' => true]);

        $priorities = [
            ['lead_tracking', 'source', 'Source'],
            ['lead_tracking', 'source_type', 'Source Type'],
            ['lead_tracking', 'product_interested', 'Product Interested'],
            ['lead_tracking', 'deal_value', 'Deal Value'],
            ['lead_tracking', 'lead_group', 'Lead Group'],
            ['additional', 'business_name', 'Business Name'],
            ['additional', 'additional_info', 'Additional Info'],
            ['additional', 'city', 'City'],
        ];
        foreach ($priorities as $i => [$section, $key, $label]) {
            FieldPriority::updateOrCreate(
                ['section' => $section, 'field_key' => $key],
                ['label' => $label, 'sequence' => $i, 'is_selected' => false]
            );
        }

        $this->command?->info('CRM settings seeded: tags, stages, groups, field priorities');
    }
}
