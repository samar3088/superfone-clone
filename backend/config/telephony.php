<?php

return [
    /*
     | Which telephony driver handles calls. "simulated" works fully offline.
     | Later: add "exotel", "twilio", "plivo" drivers and switch here (or via
     | TELEPHONY_DRIVER in .env) — the app's routing engine doesn't change.
     */
    'driver' => env('TELEPHONY_DRIVER', 'simulated'),

    // Seconds the sticky agent rings before the call opens to everyone.
    'sticky_ring_seconds' => (int) env('TELEPHONY_STICKY_RING_SECONDS', 15),
];
