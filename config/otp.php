<?php

return [
    /*
     | How the one-time password reaches the user.
     |   log    → written to storage/logs (development, no SMS cost)
     |   msg91  → real gateway; add credentials and the driver class when the
     |            client's DLT registration and sender ID are approved
     */
    'driver' => env('OTP_DRIVER', 'log'),

    'length' => (int) env('OTP_LENGTH', 6),

    // How long a code stays valid.
    'ttl_seconds' => (int) env('OTP_TTL_SECONDS', 300),

    // Wrong guesses allowed before the code is burned.
    'max_attempts' => (int) env('OTP_MAX_ATTEMPTS', 5),

    // Minimum wait between two "send code" requests for the same mobile.
    'resend_cooldown' => (int) env('OTP_RESEND_COOLDOWN', 30),

    'gateways' => [
        'msg91' => [
            'key' => env('MSG91_AUTH_KEY'),
            'sender' => env('MSG91_SENDER_ID'),
            'template' => env('MSG91_TEMPLATE_ID'),
        ],
    ],
];
