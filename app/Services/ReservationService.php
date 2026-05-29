<?php

declare(strict_types=1);

namespace App\Services;

use App\Actions\CreateReservationAction;
use App\DTOs\CreateReservationDTO;
use App\Enums\ReservationStatus;
use App\Events\ReservationCreated;
use App\Exceptions\ReservationAlreadyProcessedException;
use App\Exceptions\ReservationNotFoundException;
use App\Models\Reservation;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Orchestrates all reservation-related business operations.
 *
 * Responsibilities:
 * - Delegate creation to CreateReservationAction (concurrency-safe)
 * - Dispatch events after successful DB commits
 * - Handle admin confirm / cancel flows with their own transactions
 * - Provide query methods for admin listing
 *
 * This service is intentionally thin — it coordinates, not implements.
 */
final class ReservationService
{
    public function __construct(
        private readonly CreateReservationAction $createReservationAction,
    ) {}

    // ─── User Operations ──────────────────────────────────────────────────────

    /**
     * Creates a concurrency-safe reservation and dispatches the domain event.
     *
     * The event is dispatched AFTER the transaction commits. This is critical:
     * if we dispatched inside the transaction and the event listener failed
     * or triggered a slow operation, the transaction could time out and the
     * reservation would be lost — leaving a notification sent for nothing.
     *
     * @throws \App\Exceptions\BookOutOfStockException
     * @throws \App\Exceptions\DuplicatePendingReservationException
     */
    public function reserve(CreateReservationDTO $dto): Reservation
    {
        $reservation = $this->createReservationAction->execute($dto);

        // Safe to dispatch here — the transaction has committed successfully.
        // ReservationCreated implements ShouldDispatchAfterCommit as an
        // extra safety net for nested transaction scenarios.
        ReservationCreated::dispatch($reservation);

        return $reservation;
    }

    // ─── Admin Operations ─────────────────────────────────────────────────────

    /**
     * Returns a paginated list of all pending reservations with relations.
     */
    public function getPendingReservations(): LengthAwarePaginator
    {
        return Reservation::with(['user', 'book'])
            ->pending()
            ->latest()
            ->paginate(20);
    }

    /**
     * Confirms a pending reservation.
     *
     * No stock change is needed here — stock was already decremented when
     * the reservation was created as 'pending'.
     *
     * @throws ReservationNotFoundException
     * @throws ReservationAlreadyProcessedException
     */
    public function confirm(int $reservationId): Reservation
    {
        return DB::transaction(function () use ($reservationId): Reservation {
            $reservation = Reservation::lockForUpdate()->find($reservationId);

            if (! $reservation) {
                throw new ReservationNotFoundException($reservationId);
            }

            if ($reservation->status->isTerminal()) {
                throw new ReservationAlreadyProcessedException(
                    $reservationId,
                    $reservation->status->value
                );
            }

            $reservation->update([
                'status'       => ReservationStatus::Confirmed,
                'confirmed_at' => now(),
                'expires_at'   => null, // No longer subject to auto-expiry
            ]);

            return $reservation->fresh();
        });
    }

    /**
     * Cancels a pending reservation and restores the book's stock.
     *
     * Stock restoration and status update are wrapped in a single transaction
     * to ensure atomicity — we never want a cancelled reservation without
     * the stock being restored, or a stock increment without a cancellation.
     *
     * lockForUpdate() on the book prevents a race condition where two
     * simultaneous cancellations (e.g., admin cancel + scheduler expiry)
     * both increment the stock, causing a double-restore.
     *
     * @throws ReservationNotFoundException
     * @throws ReservationAlreadyProcessedException
     */
    public function cancel(int $reservationId): Reservation
    {
        return DB::transaction(function () use ($reservationId): Reservation {
            $reservation = Reservation::with('book')
                ->lockForUpdate()
                ->find($reservationId);

            if (! $reservation) {
                throw new ReservationNotFoundException($reservationId);
            }

            if ($reservation->status->isTerminal()) {
                throw new ReservationAlreadyProcessedException(
                    $reservationId,
                    $reservation->status->value
                );
            }

            // Restore stock atomically before marking as cancelled
            // increment() issues: UPDATE books SET stock = stock + 1 WHERE id = ?
            $reservation->book()->lockForUpdate()->first()->increment('stock');

            $reservation->update([
                'status'       => ReservationStatus::Cancelled,
                'cancelled_at' => now(),
                'expires_at'   => null,
            ]);

            return $reservation->fresh();
        });
    }
}

