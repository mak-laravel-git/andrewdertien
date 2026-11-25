<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Add members' shares of any paid toss that has not reached their wallet yet.
Schedule::command('wallet:settle')->everyTenMinutes()->withoutOverlapping();
