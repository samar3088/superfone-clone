<?php

use Illuminate\Support\Facades\Schedule;

/*
 | Leads arrive on Facebook continuously, so poll for them rather than relying
 | on anyone pressing Sync. withoutOverlapping stops a slow run from being
 | lapped by the next tick and importing the same page twice.
 */
Schedule::command('leads:sync')
    ->everyFiveMinutes()
    ->withoutOverlapping(10)
    ->runInBackground();
