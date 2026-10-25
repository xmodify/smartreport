<?php

use App\Models\BmsSession;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule automatic cleanup for expired BMS sessions every 10 minutes
Schedule::command('bms:prune-sessions')->everyTenMinutes();

// Also run standard Laravel model pruning hourly
Schedule::command('model:prune', ['--model' => [BmsSession::class]])->hourly();
