<?php

namespace App\Services\Auth\Senders;

use App\Services\Auth\Contracts\OtpSender;
use Illuminate\Support\Facades\Log;

/**
 * Development sender: writes the code to the log and lets the UI display it,
 * so the whole auth flow can be built and demoed before an SMS gateway and
 * DLT registration are in place.
 */
class LogOtpSender implements OtpSender
{
    public function send(string $mobile, string $code): void
    {
        Log::info("OTP for {$mobile}: {$code}");
    }

    public function exposesCode(): bool
    {
        // Never leak a live code outside local development.
        return ! app()->isProduction();
    }
}
