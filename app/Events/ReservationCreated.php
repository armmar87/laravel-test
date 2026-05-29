<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Reservation;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Dispatched after a reservation is successfully created and committed to DB.
 *
 * Implements ShouldDispatchAfterCommit as a safety net for nested transactions:
 * the event will only fire once the outermost transaction has committed,
 * preventing side effects (emails, notifications) for rolled-back reservations.
 */
final class ReservationCreated implements ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Reservation $reservation,
    ) {}
}

