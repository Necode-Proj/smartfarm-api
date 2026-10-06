<?php

use App\Console\Commands\SimulateSensors;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Run sensor simulation every minute
Schedule::command(SimulateSensors::class)->everyMinute();
