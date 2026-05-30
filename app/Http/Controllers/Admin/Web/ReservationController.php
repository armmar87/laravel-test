<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Web;

use App\Http\Controllers\Controller;
use App\Models\Reservation;
use App\Services\ReservationService;
use App\Exceptions\ReservationAlreadyProcessedException;
use App\Exceptions\ReservationNotFoundException;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Browser-facing admin controller for reservation management.
 *
 * Thin controller: all business logic lives in ReservationService.
 * Uses redirect + flash instead of JSON responses.
 *
 * Authorization is handled by ReservationPolicy (same as the API controller).
 */
final class ReservationController extends Controller
{
    public function __construct(
        private readonly ReservationService $reservationService,
    ) {}

    /**
     * GET /admin/reservations
     * Lists all pending reservations with pagination.
     */
    public function index(): View
    {
        $this->authorize('viewAny', Reservation::class);

        $reservations = $this->reservationService->getPendingReservations();

        return view('admin.reservations.index', compact('reservations'));
    }

    /**
     * PATCH /admin/reservations/{id}/confirm
     * Confirms a pending reservation.
     */
    public function confirm(int $reservationId): RedirectResponse
    {
        $reservation = Reservation::findOrFail($reservationId);

        $this->authorize('confirm', $reservation);

        try {
            $confirmed = $this->reservationService->confirm($reservationId);

            return redirect()
                ->route('admin.web.reservations.index')
                ->with('success', "Reservation #{$confirmed->id} has been confirmed.");

        } catch (ReservationNotFoundException $e) {
            return redirect()
                ->route('admin.web.reservations.index')
                ->with('error', $e->getMessage());

        } catch (ReservationAlreadyProcessedException $e) {
            return redirect()
                ->route('admin.web.reservations.index')
                ->with('error', $e->getMessage());
        }
    }

    /**
     * PATCH /admin/reservations/{id}/cancel
     * Cancels a pending reservation and restores stock.
     */
    public function cancel(int $reservationId): RedirectResponse
    {
        $reservation = Reservation::findOrFail($reservationId);

        $this->authorize('cancel', $reservation);

        try {
            $cancelled = $this->reservationService->cancel($reservationId);

            return redirect()
                ->route('admin.web.reservations.index')
                ->with('success', "Reservation #{$cancelled->id} has been cancelled and stock restored.");

        } catch (ReservationNotFoundException $e) {
            return redirect()
                ->route('admin.web.reservations.index')
                ->with('error', $e->getMessage());

        } catch (ReservationAlreadyProcessedException $e) {
            return redirect()
                ->route('admin.web.reservations.index')
                ->with('error', $e->getMessage());
        }
    }
}

