<?php

declare(strict_types=1);

namespace App\Providers;

use App\Events\ReservationCreated;
use App\Listeners\HandleReservationCreated;
use App\Models\Reservation;
use App\Policies\ReservationPolicy;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // ── Event → Listener mappings ─────────────────────────────────────────
        // HandleReservationCreated implements ShouldQueue, so it runs
        // asynchronously on the 'notifications' queue — never blocking the
        // HTTP response. It also only fires after the DB transaction commits
        // (ShouldDispatchAfterCommit on the event).
        Event::listen(
            ReservationCreated::class,
            HandleReservationCreated::class,
        );

        // ── Policy registration ───────────────────────────────────────────────
        // Explicitly registering the policy ensures it works regardless of
        // whether Laravel's auto-discovery can locate it.
        Gate::policy(Reservation::class, ReservationPolicy::class);
    }
}

