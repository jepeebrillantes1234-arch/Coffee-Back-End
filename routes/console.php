<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Kailangang tumatakbo ang scheduler:  php artisan schedule:work
Schedule::command('orders:expire-unpaid')->everyMinute();
Schedule::command('locations:prune')->daily();
