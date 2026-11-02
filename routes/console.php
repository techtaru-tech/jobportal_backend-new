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

/*
| Payment first, business document after: employers still owing the document
| for a paid posting are reminded on a few days after payment (see the
| command). Late morning in India, when a reminder is likely to be acted on —
| the app runs on UTC, so the time is pinned to IST explicitly.
*/
Schedule::command('postings:remind-missing-documents')
    ->dailyAt('10:30')
    ->timezone('Asia/Kolkata')
    ->withoutOverlapping();
