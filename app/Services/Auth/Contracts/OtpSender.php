<?php

namespace App\Services\Auth\Contracts;

/**
 * Delivery channel for a one-time password. Swapping the log driver for a
 * real SMS gateway is a single binding change — nothing else in the app moves.
 */
interface OtpSender
{
    public function send(string $mobile, string $code): void;

    /**
     * True when the code may be returned to the client (development only).
     * A real gateway must always return false.
     */
    public function exposesCode(): bool;
}
