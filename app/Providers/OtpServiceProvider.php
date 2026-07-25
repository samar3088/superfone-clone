<?php

namespace App\Providers;

use App\Services\Auth\Contracts\OtpSender;
use App\Services\Auth\Senders\LogOtpSender;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

class OtpServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(OtpSender::class, function () {
            return match (config('otp.driver')) {
                'log' => new LogOtpSender(),
                // 'msg91' => new Msg91OtpSender(config('otp.gateways.msg91')),
                default => throw new InvalidArgumentException(
                    'Unknown OTP driver ['.config('otp.driver').']'
                ),
            };
        });
    }
}
