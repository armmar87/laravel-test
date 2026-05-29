<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CancelReservationRequest;
use App\Http\Requests\Admin\ConfirmReservationRequest;
use App\Models\Reservation;
use App\Services\ReservationService;
use Illuminate\Http\JsonResponse;

/**
 * Handles admin reservation management operations.
 *
 * All three operations are policy-gated — only users with is_admin = true
 * can access these endpoints. Authorization is checked via $this->authorize()
 * which delegates to ReservationPolicy.
 */
class ReservationController extends Controller
{
    public function __construct(
        private readonly ReservationService $reservationService,
    ) {}

    /**
     * GET /api/admin/reservations
     *
     * Returns paginated list of all pending reservations.
     */
    public function index(): JsonResponse
    {
        $this->authorize('viewAny', Reservation::class);

        $reservations = $this->reservationService->getPendingReservations();

        return response()->json($reservations);
    }

    /**
     * PATCH /api/admin/reservations/{reservation}/confirm
     *
     * Confirms a pending reservation. Stock was already decremented on creation.
     */
    public function confirm(ConfirmReservationRequest $request, int $reservationId): JsonResponse
    {
        $reservation = Reservation::findOrFail($reservationId);

        $this->authorize('confirm', $reservation);

        $confirmed = $this->reservationService->confirm($reservationId);

        return response()->json([
            'message'     => 'Reservation confirmed.',
            'reservation' => [
                'id'           => $confirmed->id,
                'status'       => $confirmed->status->value,
                'confirmed_at' => $confirmed->confirmed_at?->toISOString(),
            ],
        ]);
    }

    /**
     * PATCH /api/admin/reservations/{reservation}/cancel
     *
     * Cancels a pending reservation and restores book stock.
     */
    public function cancel(CancelReservationRequest $request, int $reservationId): JsonResponse
    {
        $reservation = Reservation::findOrFail($reservationId);

        $this->authorize('cancel', $reservation);

        $cancelled = $this->reservationService->cancel($reservationId);

        return response()->json([
            'message'     => 'Reservation cancelled and stock restored.',
            'reservation' => [
                'id'           => $cancelled->id,
                'status'       => $cancelled->status->value,
                'cancelled_at' => $cancelled->cancelled_at?->toISOString(),
            ],
        ]);
    }
}

