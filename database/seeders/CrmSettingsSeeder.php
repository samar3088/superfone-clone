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
        $tags = [
            ['VIP', '#7c3aed', '👑'],
            ['New customer', '#2563eb', null],
            ['Call back', '#0891b2', null],
            ['Interested', '#0f7a52', '😍'],
            ['No response', '#6b7280', '🚫'],
            ['Hot lead', '#c2410c', '🔥'],
            ['Issue', '#be3a2b', '🐞'],
            ['Follow-up', '#a855f7', null],
        ];
        foreach ($tags as [$name, $color, $emoji]) {
            Tag::updateOrCreate(['name' => $name], ['color' => $color, 'emoji' => $emoji]);
        }

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
        ];
        foreach ($stages as $i => [$name, $type, $emoji]) {
            LeadStage::updateOrCreate(
                ['name' => $name],
                ['type' => $type, 'emoji' => $emoji, 'sequence' => $i + 1, 'is_active' => true]
            );
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
