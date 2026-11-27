<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// SPEC-004 IDG-R08.10, R08.11: accounts whose 30 days of grace ended.
Schedule::command('identity:close-due-accounts')->dailyAt('03:00')->withoutOverlapping();
