<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when an admin tries to confirm or cancel a reservation that
 * is already in a terminal state (confirmed or cancelled).
 *
 * A terminal reservation cannot be transitioned further — attempting
 * to do so is a domain violation, not a programming error.
 */
final class ReservationAlreadyProcessedException extends RuntimeException
{
    public function __construct(int $reservationId, string $currentStatus)
    {
        parent::__construct(
            "Reservation [{$reservationId}] is already [{$currentStatus}] and cannot be modified."
        );
    }
}

