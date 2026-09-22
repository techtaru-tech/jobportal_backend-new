<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Schedule
|--------------------------------------------------------------------------
|
| This needs one cron entry on the server, or nothing below ever runs:
|
|     * * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1
|
*/

/*
| Hourly rather than daily: `expires_at` carries a time, and a posting that
| closed at 9am should not keep taking applications until midnight. Overlap is
| prevented because the command is a single UPDATE and a second copy would
| simply match no rows — but a long-running database would still queue them up,
| so it says so explicitly.
*/
Schedule::command('postings:expire-overdue')->hourly()->withoutOverlapping();
