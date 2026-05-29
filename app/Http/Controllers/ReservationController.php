<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\DTOs\CreateReservationDTO;
use App\Http\Requests\StoreReservationRequest;
use App\Services\ReservationService;
use Illuminate\Http\JsonResponse;
use \App\Models\Reservation;

/**
 * Handles user-facing reservation operations.
 *
 * This controller is intentionally thin:
 * - Validation    → delegated to StoreReservationRequest
 * - Business logic → delegated to ReservationService
 * - Authorization → delegated to ReservationPolicy
 * - Error mapping → delegated to bootstrap/app.php exception handler
 */
class ReservationController extends Controller
{
    public function __construct(
        private readonly ReservationService $reservationService,
    ) {}

    /**
     * POST /api/reservations
     *
     * Creates a new reservation for the authenticated user.
     * Concurrency, stock validation, and duplicate checking are all
     * handled inside ReservationService → CreateReservationAction.
     */
    public function store(StoreReservationRequest $request): JsonResponse
    {
        $this->authorize('create', Reservation::class);

        $reservation = $this->reservationService->reserve(
            CreateReservationDTO::fromRequest($request)
        );

        return response()->json([
            'message'     => 'Reservation created successfully.',
            'reservation' => [
                'id'         => $reservation->id,
                'book_id'    => $reservation->book_id,
                'status'     => $reservation->status->value,
                'expires_at' => $reservation->expires_at?->toISOString(),
                'created_at' => $reservation->created_at?->toISOString(),
            ],
        ], 201);
    }
}

