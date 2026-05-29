<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\ReservationStatus;
use App\Models\Book;
use App\Models\Reservation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Finds all expired pending reservations and cancels them, restoring stock.
 *
 * ┌─────────────────────────────────────────────────────────────────────────┐
 * │  CONCURRENCY SAFETY                                                     │
 * │                                                                         │
 * │  Scenario: Admin manually cancels a reservation at the exact moment     │
 * │  the scheduler runs for the same reservation.                           │
 * │                                                                         │
 * │  Without locking:                                                       │
 * │    Both read status=pending → both restore stock → stock +2 ❌          │
 * │                                                                         │
 * │  With lockForUpdate() inside a transaction:                             │
 * │    Admin cancel acquires lock first → restores stock → commits          │
 * │    Scheduler reads status=cancelled (terminal) → skips silently ✅     │
 * │                                                                         │
 * │  This is why we don't call ReservationService::cancel() here —          │
 * │  that method throws on terminal states, which would abort the batch.   │
 * │  This action silently skips them instead.                               │
 * └─────────────────────────────────────────────────────────────────────────┘
 */
final class CancelExpiredReservationsAction
{
    /**
     * Number of reservations processed per chunk to control memory usage.
     * Prevents loading thousands of expired reservations into memory at once.
     */
    private const CHUNK_SIZE = 100;

    /**
     * Processes all expired pending reservations.
     *
     * @return int Number of reservations actually cancelled in this run.
     */
    public function execute(): int
    {
        $cancelledCount = 0;

        // chunk() prevents loading all expired reservations into memory at once.
        // We only select IDs here — the full lock+load happens inside each transaction.
        Reservation::expired()
            ->select('id')
            ->chunk(self::CHUNK_SIZE, function ($expiredReservations) use (&$cancelledCount): void {
                foreach ($expiredReservations as $expiredReservation) {
                    $wasCancelled = $this->cancelSingle((int) $expiredReservation->id);
                    if ($wasCancelled) {
                        $cancelledCount++;
                    }
                }
            });

        if ($cancelledCount > 0) {
            Log::info("Auto-cancellation: {$cancelledCount} expired reservation(s) cancelled and stock restored.");
        }

        return $cancelledCount;
    }

    /**
     * Cancels a single expired reservation inside its own transaction.
     *
     * Running each cancellation in its own transaction means one failure
     * does not roll back the entire batch. Each reservation is processed
     * independently and atomically.
     *
     * Returns true if the reservation was cancelled, false if skipped.
     */
    private function cancelSingle(int $reservationId): bool
    {
        try {
            return DB::transaction(function () use ($reservationId): bool {
                // Re-fetch with a row lock inside the transaction.
                // This prevents the admin-cancel vs scheduler race condition.
                $reservation = Reservation::lockForUpdate()->find($reservationId);

                // Guard 1: Reservation was deleted between the chunk query and now.
                if (! $reservation) {
                    return false;
                }

                // Guard 2: Already processed by admin (confirmed or cancelled)
                // between the chunk query and acquiring this lock. Skip silently.
                if ($reservation->status->isTerminal()) {
                    return false;
                }

                // Guard 3: Re-check expiry inside the lock — status may have changed
                // or expires_at may have been extended (future feature).
                if (! $reservation->isExpired()) {
                    return false;
                }

                // Lock the book row to prevent double stock-restore
                $book = Book::lockForUpdate()->find($reservation->book_id);

                if ($book) {
                    // UPDATE books SET stock = stock + 1 WHERE id = ?
                    $book->increment('stock');
                }

                $reservation->update([
                    'status'       => ReservationStatus::Cancelled,
                    'cancelled_at' => now(),
                    'expires_at'   => null,
                ]);

                Log::debug("Auto-cancelled reservation [{$reservationId}] for book [{$reservation->book_id}].");

                return true;
            });
        } catch (\Throwable $e) {
            // Log the failure but continue processing the rest of the batch.
            // One bad reservation must never abort the entire scheduled run.
            Log::error("Failed to auto-cancel reservation [{$reservationId}].", [
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}

