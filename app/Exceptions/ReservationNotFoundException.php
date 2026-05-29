<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when a reservation lookup fails or an invalid state transition
 * is attempted on a reservation (e.g., confirming an already-cancelled one).
 */
final class ReservationNotFoundException extends RuntimeException
{
    public function __construct(int $reservationId)
    {
        parent::__construct("Reservation [{$reservationId}] not found.");
    }
}

