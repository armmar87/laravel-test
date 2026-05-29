<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\ReservationCreated;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

/**
 * Handles side effects after a reservation is successfully created.
 *
 * This listener is queued (ShouldQueue) so it never blocks the HTTP response.
 * Heavy operations — emails, push notifications, external API calls — all
 * belong here, not inside ReservationService or CreateReservationAction.
 *
 * Why decouple this from the service?
 * Open/Closed Principle: adding a new side effect (e.g., Slack notification,
 * audit log entry, analytics event) requires zero changes to ReservationService.
 * You simply add a new listener and register it in AppServiceProvider.
 *
 * The event implements ShouldDispatchAfterCommit, so this listener is
 * guaranteed to run only after the DB transaction has fully committed.
 * No listener fires for a rolled-back (failed) reservation attempt.
 */
final class HandleReservationCreated implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * The queue this listener should be pushed onto.
     * Use a dedicated queue for notifications to isolate from other work.
     */
    public string $queue = 'notifications';

    /**
     * Number of times the queued listener may be attempted.
     * Prevents infinite retry loops on permanent failures (e.g., invalid email).
     */
    public int $tries = 3;

    /**
     * Handle the event.
     *
     * SerializesModels stores only the model ID in the queue payload.
     * When the job runs, Eloquent re-fetches the model from DB.
     * We use loadMissing() to eager-load relations before accessing them.
     */
    public function handle(ReservationCreated $event): void
    {
        $reservation = $event->reservation;

        // Ensure relations are loaded (they are NOT serialized into the queue payload)
        $reservation->loadMissing(['user', 'book']);

        // ── In a real production app, you would: ──────────────────────────────
        // - Mail::to($reservation->user)->send(new ReservationConfirmationMail($reservation));
        // - $reservation->user->notify(new ReservationCreatedNotification($reservation));
        // ─────────────────────────────────────────────────────────────────────

        // For now we log the event so the flow is fully traceable in tests
        Log::info('Reservation created.', [
            'reservation_id' => $reservation->id,
            'user_id'        => $reservation->user_id,
            'book_id'        => $reservation->book_id,
            'book_title'     => $reservation->book->title,
            'user_name'      => $reservation->user->name,
            'status'         => $reservation->status->value,
            'expires_at'     => $reservation->expires_at?->toISOString(),
        ]);
    }

    /**
     * Handle a job failure.
     * Called after all retries are exhausted.
     */
    public function failed(ReservationCreated $event, \Throwable $exception): void
    {
        Log::error('HandleReservationCreated listener failed.', [
            'reservation_id' => $event->reservation->id,
            'error'          => $exception->getMessage(),
        ]);
    }
}

