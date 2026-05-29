<?php

declare(strict_types=1);

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// ── Auto-cancellation of expired pending reservations ─────────────────────────
//
// Runs every minute via cron: * * * * * php /path/to/artisan schedule:run
//
// withoutOverlapping() — if a run takes >60s the next trigger is skipped,
//   preventing two processes from running the same batch concurrently.
//   Per-reservation lockForUpdate() inside the action is the final safety net.
//
// runInBackground() — the scheduler process returns immediately; the command
//   runs as a child process so other scheduled tasks are not blocked.
//
// onOneServer() — in multi-server deployments (shared cache driver required),
//   only one server executes this command per minute.
Schedule::command('reservations:cancel-expired')
    ->name('cancel-expired-reservations')
    ->description('Auto-cancel pending reservations past their 30-minute expiry window.')
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground();


