<?php

use App\Jobs\SendNudges;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// "Finish what you start": the daily inactivity check, queued, at 09:00 local time
Schedule::job(new SendNudges)
    ->dailyAt('09:00')
    ->timezone(config()->string('app.display_timezone'))
    ->name('send-nudges');
