<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('youos:send-due-reminders')->everyMinute();
Schedule::command('youos:send-end-of-day-reflection')->dailyAt('21:00');
Schedule::command('youos:export-conversations-markdown')->dailyAt('02:00');
