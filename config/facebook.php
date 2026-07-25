<?php

return [
    'app_id' => env('FACEBOOK_APP_ID'),
    'app_secret' => env('FACEBOOK_APP_SECRET'),

    /*
     | Long-lived user access token. Replace with a Business Manager System
     | User token before go-live — a personal token expires in ~60 days and
     | lead syncing would stop silently until someone reconnects.
     */
    'access_token' => env('FACEBOOK_ACCESS_TOKEN'),

    'graph_version' => env('FACEBOOK_GRAPH_VERSION', 'v21.0'),

    'graph_url' => 'https://graph.facebook.com',

    // Cache page access tokens rather than re-listing pages on every call.
    'page_token_ttl' => 1800,

    /*
     | How far back a routine sync will look, in days.
     |
     | Kept small in development so we work with a handful of recent leads
     | instead of years of history. Set to 0 for no limit — but for the
     | one-off go-live import prefer `leads:backfill`, which can park old
     | enquiries in a closed stage instead of assigning them as new work.
     */
    'sync_since_days' => (int) env('FACEBOOK_SYNC_SINCE_DAYS', 30),

    // Safety rail: pages of 100 to walk in a single run.
    'max_pages_per_sync' => (int) env('FACEBOOK_MAX_PAGES_PER_SYNC', 50),
];
