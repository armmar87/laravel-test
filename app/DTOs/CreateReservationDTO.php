<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Http\Requests\StoreReservationRequest;

/**
 * Immutable data transfer object for reservation creation.
 *
 * Using readonly properties guarantees that once this DTO is constructed
 * from a validated FormRequest, no layer can mutate the input data.
 * This makes the data flow explicit and traceable.
 */
final readonly class CreateReservationDTO
{
    public function __construct(
        public int $userId,
        public int $bookId,
    ) {}

    /**
     * Named constructor — maps a validated FormRequest to the DTO.
     *
     * Keeping this factory on the DTO means controllers stay thin:
     * they just call CreateReservationDTO::fromRequest($request) and
     * pass the DTO to the service.
     */
    public static function fromRequest(StoreReservationRequest $request): self
    {
        return new self(
            userId: (int) $request->user()->id,
            bookId:  (int) $request->validated('book_id'),
        );
    }
}

