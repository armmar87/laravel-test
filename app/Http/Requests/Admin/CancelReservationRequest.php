<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Authorizes and validates an admin request to cancel a reservation.
 *
 * Like ConfirmReservationRequest, the reservation is identified via
 * route model binding — no body payload is required.
 */
class CancelReservationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [];
    }
}

