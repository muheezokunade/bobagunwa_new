<?php

use App\Services\IntegrityMonitor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;

// Namecheap cron: run `php artisan schedule:run` (see docs/09-deployment-runbook.md).
Schedule::command('boba:ledger:verify')->dailyAt('01:30');
Schedule::command('boba:backup:create --keep=14')->dailyAt('02:00')->when(fn () => (string) config('boba.backup.public_key') !== '');
Schedule::call(fn () => app(IntegrityMonitor::class)->checkOverdue())->hourly()->name('boba:integrity-overdue');
Schedule::call(function () {
    DB::table('sessions')->where('last_activity', '<', now()->subDays(2)->timestamp)->delete();
    DB::table('cache')->where('expiration', '<', now()->timestamp)->delete();
})->hourly()->name('boba:prune-ephemeral');
