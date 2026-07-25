<?php

namespace App\LeadSources;

/**
 * Facebook Lead Ads source.
 *
 * Simulated until the client provides a Meta App (App ID + Secret) with the
 * leads_retrieval and pages_show_list permissions. The shapes returned here
 * mirror the Graph API responses, so going live means replacing the bodies of
 * these three methods and nothing else:
 *   GET /me/accounts             → pages()
 *   GET /{page-id}/leadgen_forms → forms()
 *   GET /{form-id}/leads         → fetchLeads()
 */
class FacebookLeadSource
{
    public function account(): array
    {
        return ['name' => 'Shank K Vasudev', 'connected' => true];
    }

    public function pages(): array
    {
        return [
            ['id' => 'pg_gevents', 'name' => 'G Events Unlimited'],
            ['id' => 'pg_planawedding', 'name' => 'Planawedding.in'],
        ];
    }

    public function forms(string $pageId): array
    {
        return match ($pageId) {
            'pg_gevents' => [
                ['id' => 'fm_1', 'name' => 'G Events Unlimited'],
                ['id' => 'fm_2', 'name' => 'B2B_META_FORM_2_43_CDElites'],
                ['id' => 'fm_3', 'name' => 'Jan 29th CRM Lead form'],
            ],
            'pg_planawedding' => [
                ['id' => 'fm_6', 'name' => 'Buy Lead & APP'],
                ['id' => 'fm_7', 'name' => 'Wedding Enquiry Form'],
            ],
            default => [],
        };
    }

    /**
     * Leads submitted against a form since the last sync.
     *
     * @return array<int, array{name: string, mobile: string, email: ?string, city: ?string}>
     */
    public function fetchLeads(string $formId, ?string $since = null): array
    {
        return [];
    }
}
