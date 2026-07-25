<?php

namespace App\LeadSources;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Facebook Lead Ads, talking to the real Graph API.
 *
 *   GET /me                        → account()
 *   GET /me/accounts               → pages()  (also yields per-page tokens)
 *   GET /{page-id}/leadgen_forms   → forms()
 *   GET /{form-id}/leads           → fetchLeads()
 */
class FacebookLeadSource
{
    private function base(): string
    {
        return rtrim(config('facebook.graph_url'), '/').'/'.config('facebook.graph_version');
    }

    private function userToken(): string
    {
        $token = (string) config('facebook.access_token');

        if ($token === '') {
            throw new RuntimeException('No Facebook access token configured. Set FACEBOOK_ACCESS_TOKEN.');
        }

        return $token;
    }

    /** @return array<string, mixed> */
    private function get(string $path, array $query): array
    {
        $response = Http::timeout(20)->retry(2, 300)->get($this->base().$path, $query);

        if ($response->failed()) {
            $error = $response->json('error.message') ?? $response->body();

            throw new RuntimeException("Facebook API error: {$error}");
        }

        return $response->json() ?? [];
    }

    public function account(): array
    {
        try {
            $me = $this->get('/me', ['access_token' => $this->userToken(), 'fields' => 'id,name']);

            return ['name' => $me['name'] ?? 'Facebook account', 'connected' => true];
        } catch (RuntimeException) {
            // A dead or missing token should render as "not connected", not a crash.
            return ['name' => null, 'connected' => false];
        }
    }

    /** Pages the token can administer, with their access tokens cached. */
    public function pages(): array
    {
        $pages = Cache::remember('fb.pages', config('facebook.page_token_ttl'), function () {
            $data = $this->get('/me/accounts', [
                'access_token' => $this->userToken(),
                'fields' => 'id,name,access_token,picture{url}',
                'limit' => 100,
            ])['data'] ?? [];

            return collect($data)->map(fn (array $p) => [
                'id' => $p['id'],
                'name' => $p['name'],
                'picture' => $p['picture']['data']['url'] ?? null,
                'description' => null,
                'token' => $p['access_token'],
            ])->all();
        });

        // The page token never leaves the server.
        return collect($pages)->map(fn (array $p) => collect($p)->except('token')->all())->all();
    }

    public function page(string $pageId): ?array
    {
        return collect($this->pages())->firstWhere('id', $pageId);
    }

    private function pageToken(string $pageId): string
    {
        $pages = Cache::get('fb.pages');

        if (! $pages) {
            $this->pages();                       // warms the cache
            $pages = Cache::get('fb.pages', []);
        }

        $page = collect($pages)->firstWhere('id', $pageId);

        if (! $page) {
            throw new RuntimeException("No access to Facebook page {$pageId}.");
        }

        return $page['token'];
    }

    /** Lead forms on a page. Archived forms are hidden — they cannot receive leads. */
    public function forms(string $pageId): array
    {
        $data = $this->get("/{$pageId}/leadgen_forms", [
            'access_token' => $this->pageToken($pageId),
            'fields' => 'id,name,status,leads_count',
            'limit' => 100,
        ])['data'] ?? [];

        return collect($data)
            ->reject(fn (array $f) => ($f['status'] ?? '') === 'ARCHIVED')
            ->map(fn (array $f) => [
                'id' => $f['id'],
                'name' => $f['name'],
                'leads_count' => $f['leads_count'] ?? 0,
            ])
            ->values()
            ->all();
    }

    /**
     * Leads submitted against a form, newest first.
     *
     * @param  string|null  $since  unix timestamp; only newer leads are returned
     * @return array<int, array{external_id: string, name: string, mobile: string, email: ?string, city: ?string, custom_data: array, created_time: string}>
     */
    public function fetchLeads(string $formId, string $pageId, ?string $since = null, int $limit = 100): array
    {
        $query = [
            'access_token' => $this->pageToken($pageId),
            'fields' => 'id,created_time,field_data',
            'limit' => $limit,
        ];

        if ($since) {
            $query['filtering'] = json_encode([[
                'field' => 'time_created',
                'operator' => 'GREATER_THAN',
                'value' => $since,
            ]]);
        }

        $rows = $this->get("/{$formId}/leads", $query)['data'] ?? [];

        return collect($rows)->map(fn (array $row) => $this->normalise($row))->filter()->values()->all();
    }

    /**
     * Flatten Facebook's field_data into our lead shape.
     *
     * Field names differ per form — "full_name" on one, "first_name" plus
     * "last_name" on another, and free-text questions anywhere — so map the
     * ones we understand and keep the remainder as custom data.
     */
    private function normalise(array $row): ?array
    {
        $fields = [];
        foreach ($row['field_data'] ?? [] as $field) {
            $fields[strtolower($field['name'] ?? '')] = ($field['values'] ?? [''])[0] ?? '';
        }

        $mobile = $this->normaliseMobile(
            $fields['phone_number'] ?? $fields['phone'] ?? $fields['mobile_number'] ?? ''
        );

        // Without a usable mobile there is no way to match or contact them.
        if ($mobile === null) {
            return null;
        }

        $name = trim($fields['full_name'] ?? trim(($fields['first_name'] ?? '').' '.($fields['last_name'] ?? '')));

        $known = ['full_name', 'first_name', 'last_name', 'phone_number', 'phone',
            'mobile_number', 'email', 'city'];

        return [
            'external_id' => (string) $row['id'],
            'name' => $name !== '' ? $name : 'Unknown',
            'mobile' => $mobile,
            'email' => filter_var($fields['email'] ?? '', FILTER_VALIDATE_EMAIL) ?: null,
            'city' => $fields['city'] ?? null,
            'custom_data' => collect($fields)->except($known)->filter()->all(),
            'created_time' => $row['created_time'] ?? now()->toIso8601String(),
        ];
    }

    /** Facebook returns E.164 (+919876543210); we store the 10-digit number. */
    private function normaliseMobile(string $raw): ?string
    {
        $digits = preg_replace('/\D/', '', $raw) ?? '';

        if (strlen($digits) > 10) {
            $digits = substr($digits, -10);
        }

        return preg_match('/^[6-9]\d{9}$/', $digits) === 1 ? $digits : null;
    }
}
