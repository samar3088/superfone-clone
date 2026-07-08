<?php

namespace App\LeadSources;

/**
 * Facebook Lead Ads source.
 *
 * SIMULATED for now: returns a fixed connected account with its pages and
 * lead forms, shaped exactly like the Meta Graph API responses.
 *
 * To go live, the client must provide a Meta App (App ID + Secret) with the
 * `leads_retrieval` + `pages_show_list` permissions and complete FB Login.
 * Then this class swaps its stub methods for Graph API calls:
 *   GET /me/accounts                 → pages()
 *   GET /{page-id}/leadgen_forms     → forms()
 * Nothing else in the app changes.
 */
class FacebookLeadSource
{
    public function account(): array
    {
        return [
            'name' => 'Shank K Vasudev',
            'connected' => true,
        ];
    }

    public function pages(): array
    {
        return [
            ['id' => 'pg_gevents', 'name' => 'G Events Unlimited', 'initial' => 'G', 'color' => '#c026d3'],
            ['id' => 'pg_planawedding', 'name' => 'Planawedding.in', 'initial' => 'P', 'color' => '#7c3aed'],
        ];
    }

    public function forms(string $pageId): array
    {
        $forms = [
            'pg_gevents' => [
                ['id' => 'fm_1', 'name' => 'G Events Unlimited'],
                ['id' => 'fm_2', 'name' => 'B2B_META_FORM_2_43_CDElites'],
                ['id' => 'fm_3', 'name' => 'B2B_META_FORM_1_43_CDElites'],
                ['id' => 'fm_4', 'name' => 'B2B_META_FORM_43_CDElites'],
                ['id' => 'fm_5', 'name' => 'Jan 29th CRM Lead form'],
            ],
            'pg_planawedding' => [
                ['id' => 'fm_6', 'name' => 'Buy Lead & APP'],
                ['id' => 'fm_7', 'name' => 'Wedding Enquiry Form'],
                ['id' => 'fm_8', 'name' => 'Venue Booking Leads'],
            ],
        ];

        return $forms[$pageId] ?? [];
    }
}
