<?php

use Illuminate\Support\Facades\Schedule;

/*
 | Leads arrive on Facebook continuously, so poll for them rather than relying
 | on anyone pressing Sync.
 |
 | Each run only asks for what has appeared since the last successful one, so
 | after the first pull this is a couple of hours of leads, not the 30-day
 | window and not the whole form. withoutOverlapping stops a slow run being
 | lapped by the next tick and importing the same page twice.
 |
 | Needs a cron entry on the server, or nothing fires:
 |   * * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1
 */
Schedule::command('leads:sync')
    ->everyTwoHours()
    ->withoutOverlapping(115)
    ->runInBackground();
