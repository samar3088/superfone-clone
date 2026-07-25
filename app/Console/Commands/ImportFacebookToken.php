<?php

namespace App\Console\Commands;

use App\Services\Support\SettingsService;
use App\Support\Settings;
use Illuminate\Console\Command;

/**
 * Moves the bootstrap token from .env into the encrypted store, once.
 *
 * Also the way to rotate from a shell if the Settings screen is unreachable.
 */
class ImportFacebookToken extends Command
{
    protected $signature = 'facebook:token
        {token? : The token; omitted, the value from config/.env is used}';

    protected $description = 'Save the Facebook access token to encrypted storage';

    public function handle(SettingsService $settings): int
    {
        $token = $this->argument('token') ?: (string) config('facebook.access_token');

        if ($token === '') {
            $this->error('No token given and none in config. Pass one as an argument.');

            return self::FAILURE;
        }

        $settings->set(Settings::FACEBOOK_TOKEN, $token);

        // Never print the token itself — this output lands in scrollback and CI logs.
        $this->info('Token saved, encrypted. Fingerprint: '.substr(hash('sha256', $token), 0, 12));
        $this->line('You can now clear FACEBOOK_ACCESS_TOKEN from .env.');

        return self::SUCCESS;
    }
}
