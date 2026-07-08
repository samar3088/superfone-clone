<?php

namespace App\Telephony;

use InvalidArgumentException;

class Telephony
{
    /** Resolve the configured (or an explicitly named) driver. */
    public static function driver(?string $name = null): TelephonyProvider
    {
        $name = $name ?? config('telephony.driver', 'simulated');

        return match ($name) {
            'simulated' => new SimulatedProvider(),
            // 'exotel' => new ExotelProvider(),   ← add when a provider is chosen
            // 'twilio' => new TwilioProvider(),
            default => throw new InvalidArgumentException("Unknown telephony driver [$name]"),
        };
    }
}
