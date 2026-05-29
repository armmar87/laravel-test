<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when a reservation is attempted on a book with no available stock.
 *
 * This is a domain exception — it signals a business rule violation,
 * not a programming error. The global exception handler maps this to HTTP 422.
 */
final class BookOutOfStockException extends RuntimeException
{
    public function __construct(int $bookId)
    {
        parent::__construct("Book [{$bookId}] is out of stock.");
    }
}

