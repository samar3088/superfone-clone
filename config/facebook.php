<?php

return [
    'app_id' => env('FACEBOOK_APP_ID'),
    'app_secret' => env('FACEBOOK_APP_SECRET'),

    /*
     | Bootstrap token only.
     |
     | The live token belongs in the app_settings store, where it is encrypted
     | at rest and can be rotated from Settings without a deploy or a shell.
     | FacebookLeadSource reads that first and only falls back to here, so this
     | should stay empty outside of local setup — anything in .env is readable
     | by every process on the box and prints in a stack trace.
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
