<?php

declare(strict_types=1);

namespace App\Enums;

enum ReservationStatus: string
{
    case Pending   = 'pending';
    case Confirmed = 'confirmed';
    case Cancelled = 'cancelled';

    /**
     * Returns a human-readable label for display in admin UIs or notifications.
     */
    public function label(): string
    {
        return match($this) {
            self::Pending   => 'Pending',
            self::Confirmed => 'Confirmed',
            self::Cancelled => 'Cancelled',
        };
    }

    /**
     * Only pending reservations are eligible for state transitions.
     */
    public function isPending(): bool
    {
        return $this === self::Pending;
    }

    /**
     * Terminal states — no further transitions allowed.
     */
    public function isTerminal(): bool
    {
        return $this === self::Confirmed || $this === self::Cancelled;
    }
}

