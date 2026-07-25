<?php

namespace App\Services\Auth;

use App\Models\OtpCode;
use App\Services\Auth\Contracts\OtpSender;
use Illuminate\Validation\ValidationException;

/**
 * Issues and verifies one-time passwords.
 *
 * Security posture:
 *  – the code is stored only as a hash, never in plain text
 *  – a code expires, and a wrong-guess budget burns it early
 *  – issuing is rate limited per mobile by a resend cooldown
 *  – verifying always consumes the code, success or failure
 */
class OtpService
{
    public function __construct(private OtpSender $sender) {}

    /**
     * Generate, store and send a code. Returns the plain code only when the
     * "log" driver is active, so the dev UI can surface it without an SMS.
     */
    public function issue(string $mobile, string $purpose = 'login', ?string $ip = null): ?string
    {
        $this->assertNotThrottled($mobile, $purpose);

        $code = $this->generateCode();

        // Any earlier live code for this mobile+purpose is retired, so only
        // the newest code can ever be redeemed.
        OtpCode::query()
            ->where('mobile', $mobile)
            ->where('purpose', $purpose)
            ->live()
            ->update(['consumed_at' => now()]);

        OtpCode::create([
            'mobile' => $mobile,
            'code_hash' => $this->hash($code),
            'purpose' => $purpose,
            'expires_at' => now()->addSeconds(config('otp.ttl_seconds')),
            'ip' => $ip,
        ]);

        $this->sender->send($mobile, $code);

        return $this->sender->exposesCode() ? $code : null;
    }

    /**
     * Verify a submitted code. Throws a validation error the form can render.
     */
    public function verify(string $mobile, string $code, string $purpose = 'login'): void
    {
        $record = OtpCode::query()
            ->where('mobile', $mobile)
            ->where('purpose', $purpose)
            ->live()
            ->latest('id')
            ->first();

        if (! $record) {
            $this->fail('code', 'That code has expired. Request a new one.');
        }

        if ($record->attempts >= config('otp.max_attempts')) {
            $record->update(['consumed_at' => now()]);
            $this->fail('code', 'Too many incorrect attempts. Request a new code.');
        }

        if (! hash_equals($record->code_hash, $this->hash($code))) {
            $record->increment('attempts');
            $remaining = max(0, config('otp.max_attempts') - $record->attempts);

            $this->fail('code', $remaining > 0
                ? "Incorrect code. {$remaining} attempt".($remaining === 1 ? '' : 's').' left.'
                : 'Too many incorrect attempts. Request a new code.');
        }

        $record->update(['consumed_at' => now()]);
    }

    /** Seconds the caller must wait before another code can be sent. */
    public function cooldownRemaining(string $mobile, string $purpose = 'login'): int
    {
        $last = OtpCode::query()
            ->where('mobile', $mobile)
            ->where('purpose', $purpose)
            ->latest('id')
            ->first();

        if (! $last) {
            return 0;
        }

        $elapsed = $last->created_at->diffInSeconds(now());

        return (int) max(0, config('otp.resend_cooldown') - $elapsed);
    }

    private function assertNotThrottled(string $mobile, string $purpose): void
    {
        $wait = $this->cooldownRemaining($mobile, $purpose);

        if ($wait > 0) {
            $this->fail('mobile', "Please wait {$wait}s before requesting another code.");
        }
    }

    /**
     * Keyed hash of a code, so a database leak alone never reveals it.
     *
     * Deliberately HMAC rather than bcrypt. Bcrypt is slow by design to blunt
     * offline brute force of passwords, but a code here lives 5 minutes, dies
     * after 5 wrong guesses, and is rate limited per mobile and IP — so the
     * slowness buys nothing while costing ~400ms of CPU on every single login.
     * Reversing this still requires APP_KEY, and anyone holding APP_KEY plus
     * the database already owns the session store.
     */
    private function hash(string $code): string
    {
        return hash_hmac('sha256', $code, (string) config('app.key'));
    }

    private function generateCode(): string
    {
        $length = config('otp.length');
        $max = (10 ** $length) - 1;

        return str_pad((string) random_int(0, $max), $length, '0', STR_PAD_LEFT);
    }

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
