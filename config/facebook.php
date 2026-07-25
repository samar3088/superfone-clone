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
];
