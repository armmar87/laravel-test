<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when a user already has a pending reservation for the same book.
 *
 * Important: This check alone is NOT enough to prevent duplicates under
 * concurrent requests. It must always be performed inside a DB transaction
 * with a lockForUpdate() on the book row to ensure serialized execution.
 *
 * Without the lock, two simultaneous requests could both pass this check
 * (neither seeing the other's pending reservation yet) and create duplicates.
 */
final class DuplicatePendingReservationException extends RuntimeException
{
    public function __construct(int $userId, int $bookId)
    {
        parent::__construct(
            "User [{$userId}] already has a pending reservation for book [{$bookId}]."
        );
    }
}

