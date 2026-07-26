<?php

namespace App\LeadSources;

use App\Models\Integration;
use App\Services\Support\SettingsService;
use App\Support\Settings;
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

    public function __construct(private SettingsService $settings) {}

    /**
     * The encrypted store wins; config is only a bootstrap for a fresh install.
     */
    private function userToken(): string
    {
        $token = (string) ($this->settings->get(Settings::FACEBOOK_TOKEN)
            ?: config('facebook.access_token'));

        if ($token === '') {
            throw new RuntimeException(
                'No Facebook access token saved. Add one under Settings → Integrations.'
            );
        }

        return $token;
    }

    /** @return array<string, mixed> */
    private function get(string $path, array $query): array
    {
        /*
         | throw: false matters. By default retry() raises its own
         | RequestException once attempts run out, which would sail straight
         | past every `catch (RuntimeException)` in this class — so a dead token
         | would 500 the page instead of rendering as "not connected".
         */
        $response = Http::timeout(20)->retry(2, 300, throw: false)->get($this->base().$path, $query);

        if ($response->failed()) {
            $error = $response->json('error.message') ?? $response->body();

            throw new RuntimeException("Facebook API error: {$error}");
        }

        return $response->json() ?? [];
    }

    /**
     * Check a token against Graph before we commit to it.
     *
     * Saving an unverified string is how lead syncing dies quietly: the token
     * looks fine in the database, and nobody finds out until the next run
     * fails hours later. This proves the token works and reports whose account
     * it belongs to, so the person pasting it can confirm it is the right one.
     *
     * @return array{name: string, pages: int}
     *
     * @throws RuntimeException when Facebook rejects it
     */
    public function verifyToken(string $token): array
    {
        $me = Http::timeout(15)->get($this->base().'/me', [
            'access_token' => $token,
            'fields' => 'id,name',
        ]);

        if ($me->failed()) {
            throw new RuntimeException(
                $me->json('error.message') ?? 'Facebook rejected this token.'
            );
        }

        // A token that cannot see any pages cannot read leads either, so the
        // count is worth surfacing rather than discovering later.
        $accounts = Http::timeout(15)->get($this->base().'/me/accounts', [
            'access_token' => $token,
            'fields' => 'id',
            'limit' => 100,
        ]);

        return [
            'name' => $me->json('name') ?: 'Facebook account',
            'pages' => count($accounts->json('data') ?? []),
        ];
    }

    /** Drop everything derived from the old token. */
    public function forgetCaches(): void
    {
        Cache::forget('fb.pages');

        Integration::query()
            ->whereNotNull('external_page_id')
            ->pluck('external_page_id')
            ->each(fn (string $id) => Cache::forget("fb.page.profile.{$id}"));
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
                'fields' => 'id,name,access_token',
                'limit' => 100,
            ])['data'] ?? [];

            return collect($data)->map(fn (array $p) => [
                'id' => $p['id'],
                'name' => trim($p['name']),
                // Deliberately not the CDN url Facebook returns: that is ~540
                // characters and carries an expiry, so a stored copy would
                // rot within days. This endpoint is stable and redirects to
                // whatever the current avatar is.
                'picture' => $this->base().'/'.$p['id'].'/picture?type=square',
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

    /**
     * Cover photo and blurb for the page preview.
     *
     * Fetched on demand and cached briefly rather than stored: Facebook's cover
     * urls are signed and expire within days, so a copy in our database would
     * turn into a broken image. The avatar is the stable /picture endpoint and
     * keeps working regardless.
     *
     * @return array{name: string, picture: string, cover: ?string, about: ?string}|null
     */
    public function pageProfile(string $pageId): ?array
    {
        return Cache::remember("fb.page.profile.{$pageId}", 1800, function () use ($pageId) {
            try {
                $data = $this->get("/{$pageId}", [
                    'access_token' => $this->pageToken($pageId),
                    'fields' => 'id,name,about,description,cover{source}',
                ]);
            } catch (RuntimeException) {
                // A preview is decorative — a dead token should not 500 the page.
                return null;
            }

            return [
                'name' => $data['name'] ?? '',
                'picture' => $this->base().'/'.$pageId.'/picture?type=large',
                'cover' => $data['cover']['source'] ?? null,
                'about' => $data['about'] ?? $data['description'] ?? null,
            ];
        });
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
     * Walk every lead on a form, handing each page to the callback.
     *
     * Graph caps a response at 100 rows and returns a cursor for the rest, so
     * a single request silently truncates a busy form. This follows the cursor
     * to the end, and streams page by page so a form with thousands of leads
     * never has to sit in memory at once.
     *
     * @param  string|null  $since  unix timestamp; only newer leads are returned
     * @param  callable  $handler  fn(array $leads, int $pageNumber): void
     * @return array{pages: int, rows: int, complete: bool}
     */
    public function eachLeadPage(
        string $formId,
        string $pageId,
        ?string $since,
        callable $handler,
        int $pageSize = 100,
        ?int $maxPages = null,
    ): array {
        $maxPages ??= config('facebook.max_pages_per_sync');

        $query = [
            'access_token' => $this->pageToken($pageId),
            'fields' => 'id,created_time,field_data',
            'limit' => $pageSize,
        ];

        if ($since) {
            $query['filtering'] = json_encode([[
                'field' => 'time_created',
                'operator' => 'GREATER_THAN',
                'value' => $since,
            ]]);
        }

        $url = $this->base()."/{$formId}/leads";
        $pages = 0;
        $rows = 0;

        while ($url && $pages < $maxPages) {
            // throw: false so the failure check below reports the Graph error
            // message, rather than retry() raising a bare RequestException.
            $request = Http::timeout(30)->retry(2, 500, throw: false);

            /*
             | Only the first call builds its own query. Follow-up pages are
             | complete urls carrying the cursor, token and filters already —
             | and handing Guzzle a query array replaces the url's query string
             | outright, which would strip every one of them.
             */
            $response = $query === null ? $request->get($url) : $request->get($url, $query);

            if ($response->failed()) {
                throw new RuntimeException('Facebook API error: '
                    .($response->json('error.message') ?? $response->body()));
            }

            $body = $response->json() ?? [];
            $leads = collect($body['data'] ?? [])
                ->map(fn (array $row) => $this->normalise($row))
                ->filter()
                ->values()
                ->all();

            $pages++;
            $rows += count($leads);

            if ($leads !== []) {
                $handler($leads, $pages);
            }

            $url = $body['paging']['next'] ?? null;
            $query = null;
        }

        return [
            'pages' => $pages,
            'rows' => $rows,
            // False means we stopped on the safety rail with more to collect,
            // so the caller must not treat this window as fully drained.
            'complete' => $url === null,
        ];
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

        /*
         | Some forms send full_name, others first_name and last_name. Where the
         | form already tells us the split, keep it — guessing it back later
         | from the joined string is how "Asha Devi Rao" loses her middle name.
         */
        $first = trim((string) ($fields['first_name'] ?? ''));
        $last = trim((string) ($fields['last_name'] ?? ''));

        $name = trim((string) ($fields['full_name'] ?? '')) ?: trim("{$first} {$last}");

        $known = ['full_name', 'first_name', 'last_name', 'phone_number', 'phone',
            'mobile_number', 'email', 'city'];

        return [
            'external_id' => (string) $row['id'],
            'name' => $name !== '' ? $name : 'Unknown',
            /*
             | Passed on only when the form gave both halves. One half is not a
             | split, and half a split written over a whole name would lose the
             | other half — so in that case the model derives it instead.
             */
            'first_name' => $first !== '' && $last !== '' ? $first : null,
            'last_name' => $first !== '' && $last !== '' ? $last : null,
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
