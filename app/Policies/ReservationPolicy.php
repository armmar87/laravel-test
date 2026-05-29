<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Reservation;
use App\Models\User;

/**
 * Authorization policy for Reservation operations.
 *
 * Laravel automatically resolves this policy for the Reservation model
 * when registered (or auto-discovered). Controllers call $this->authorize()
 * which delegates here — keeping auth logic out of controllers entirely.
 */
class ReservationPolicy
{
    /**
     * Any authenticated user can create a reservation.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Only admins can view the full pending reservations list.
     */
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Only admins can confirm a pending reservation.
     */
    public function confirm(User $user, Reservation $reservation): bool
    {
        return $user->isAdmin();
    }

    /**
     * Only admins can manually cancel a reservation.
     */
    public function cancel(User $user, Reservation $reservation): bool
    {
        return $user->isAdmin();
    }
}

