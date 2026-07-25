<?php

namespace App\Services\Support;

use App\Models\AppSetting;
use App\Support\Settings;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

/**
 * Runtime settings, read far more often than they are written.
 *
 * Values marked encrypted are stored as ciphertext and only ever decrypted on
 * the server. The whole table is cached as one entry so a page render costs a
 * single query at most, and any write clears it.
 */
class SettingsService
{
    private const CACHE_KEY = 'app.settings';

    private const CACHE_TTL = 3600;

    public function get(string $key, mixed $default = null): mixed
    {
        $raw = $this->all()[$key] ?? null;

        if ($raw === null) {
            return $default;
        }

        if (! Settings::isEncrypted($key)) {
            return $raw;
        }

        try {
            return Crypt::decryptString($raw);
        } catch (DecryptException) {
            /*
             | Almost always an APP_KEY change: the ciphertext is now unreadable.
             | Fall back rather than crash the page, but say so loudly — the
             | value has to be re-entered.
             */
            Log::warning("Could not decrypt setting [{$key}] — it must be set again.");

            return $default;
        }
    }

    public function boolean(string $key, bool $default = false): bool
    {
        $value = $this->get($key);

        return $value === null ? $default : filter_var($value, FILTER_VALIDATE_BOOL);
    }

    public function set(string $key, mixed $value): void
    {
        $stored = match (true) {
            $value === null => null,
            is_bool($value) => $value ? '1' : '0',
            Settings::isEncrypted($key) => Crypt::encryptString((string) $value),
            default => (string) $value,
        };

        AppSetting::updateOrCreate(
            ['key' => $key],
            ['value' => $stored, 'is_encrypted' => Settings::isEncrypted($key)],
        );

        $this->flush();
    }

    public function forget(string $key): void
    {
        AppSetting::where('key', $key)->delete();

        $this->flush();
    }

    /** True when a value is present, without exposing what it is. */
    public function has(string $key): bool
    {
        return filled($this->all()[$key] ?? null);
    }

    /** @return array<string, string|null> raw rows, ciphertext still sealed */
    private function all(): array
    {
        return Cache::remember(
            self::CACHE_KEY,
            self::CACHE_TTL,
            fn () => AppSetting::pluck('value', 'key')->all(),
        );
    }

    public function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
