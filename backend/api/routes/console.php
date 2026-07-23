<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('audit:purge-expired-login-failures --execute --batch=500')
    ->dailyAt('02:15')
    ->timezone('Asia/Jakarta')
    ->withoutOverlapping();

Schedule::command('content:purge-orphan-media --execute --batch=200')
    ->dailyAt('02:45')
    ->timezone('Asia/Jakarta')
    ->withoutOverlapping();
