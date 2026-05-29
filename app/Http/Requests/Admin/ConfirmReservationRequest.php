<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Authorizes and validates an admin request to confirm a reservation.
 *
 * The reservation_id comes from the route parameter, not the request body,
 * so no body validation rules are needed here. Authorization is handled
 * by ReservationPolicy via the controller's middleware.
 */
class ConfirmReservationRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Policy check is applied in the controller via $this->authorize()
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        // The reservation_id is a route parameter validated by route model binding.
        // No request body is needed to confirm a reservation.
        return [];
    }
}

