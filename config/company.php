<?php

return [
    // Short name used across the admin UI.
    'name' => env('COMPANY_NAME', 'VVT'),

    /*
     | Registered entity name — email footers, exports, and the seeded
     | organisation on the Teams screen. Stored in its natural case; screens
     | that want it shouted apply their own text-transform.
     */
    'legal_name' => env('COMPANY_LEGAL_NAME', 'Variety Vintage Technologies Private Limited'),
];
