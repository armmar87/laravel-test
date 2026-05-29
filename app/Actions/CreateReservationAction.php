<?php

declare(strict_types=1);

namespace App\Actions;

use App\DTOs\CreateReservationDTO;
use App\Enums\ReservationStatus;
use App\Exceptions\BookOutOfStockException;
use App\Exceptions\DuplicatePendingReservationException;
use App\Models\Book;
use App\Models\Reservation;
use Illuminate\Support\Facades\DB;

/**
 * Handles the creation of a reservation with full concurrency safety.
 *
 * ┌─────────────────────────────────────────────────────────────────────┐
 * │  CONCURRENCY STRATEGY                                               │
 * │                                                                     │
 * │  Problem: Two simultaneous requests for the last copy of a book.   │
 * │                                                                     │
 * │  Without locking:                                                   │
 * │    Both read stock = 1, both pass the check, both decrement         │
 * │    → stock = -1, two reservations created (overselling)            │
 * │                                                                     │
 * │  With lockForUpdate() inside a transaction:                         │
 * │    Request A acquires row lock → checks stock → decrements          │
 * │    Request B BLOCKS until A commits                                 │
 * │    Request B reads stock = 0 → throws BookOutOfStockException ✅   │
 * │                                                                     │
 * │  Why duplicate check alone is not enough:                           │
 * │    Without the lock, two requests can simultaneously pass the       │
 * │    pending-reservation check (neither sees the other's INSERT yet)  │
 * │    → creating two pending reservations for the same user+book.     │
 * │    lockForUpdate() serializes both the stock check AND the          │
 * │    duplicate check on the same critical section.                    │
 * └─────────────────────────────────────────────────────────────────────┘
 */
final class CreateReservationAction
{
    /**
     * @throws BookOutOfStockException
     * @throws DuplicatePendingReservationException
     */
    public function execute(CreateReservationDTO $dto): Reservation
    {
        return DB::transaction(function () use ($dto): Reservation {

            // ── Step 1: Acquire a row-level exclusive lock ─────────────────
            //
            // lockForUpdate() translates to: SELECT * FROM books WHERE id = ? FOR UPDATE
            //
            // This blocks any other transaction attempting to lock or modify
            // this book row until our transaction commits or rolls back.
            // All concurrent reservation attempts for this book are serialized here.
            $book = Book::lockForUpdate()->findOrFail($dto->bookId);

            // ── Step 2: Check stock (inside the lock) ──────────────────────
            //
            // We are now the ONLY transaction that can read and modify this
            // book's stock. The value we see is authoritative.
            if ($book->stock < 1) {
                throw new BookOutOfStockException($dto->bookId);
            }

            // ── Step 3: Check for duplicate pending reservation ────────────
            //
            // Also performed inside the lock so no two concurrent requests
            // can both pass this check simultaneously. Without lockForUpdate(),
            // two requests could race past here before either INSERT completes.
            $alreadyExists = Reservation::query()
                ->where('user_id', $dto->userId)
                ->where('book_id', $dto->bookId)
                ->where('status', ReservationStatus::Pending)
                ->exists();

            if ($alreadyExists) {
                throw new DuplicatePendingReservationException($dto->userId, $dto->bookId);
            }

            // ── Step 4: Decrement stock atomically ─────────────────────────
            //
            // decrement() issues: UPDATE books SET stock = stock - 1 WHERE id = ?
            // This is atomic at the SQL level and does not suffer from
            // the read-modify-write race that a $book->stock-- would have.
            $book->decrement('stock');

            // ── Step 5: Create the reservation ────────────────────────────
            //
            // expires_at is set to 30 minutes from now. The scheduler will
            // cancel and restore stock for any reservation still pending
            // after this window.
            return Reservation::create([
                'user_id'    => $dto->userId,
                'book_id'    => $dto->bookId,
                'status'     => ReservationStatus::Pending,
                'expires_at' => now()->addMinutes(30),
            ]);

            // Transaction commits here → lock is released.
        });
    }
}

