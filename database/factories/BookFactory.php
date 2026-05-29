<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Book;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Book>
 */
class BookFactory extends Factory
{
    protected $model = Book::class;

    public function definition(): array
    {
        return [
            'title'  => fake()->sentence(3, true),
            'author' => fake()->name(),
            'isbn'   => '978-' . fake()->unique()->numerify('#########'),
            'stock'  => fake()->numberBetween(1, 20),
        ];
    }

    /** State: no available stock — for out-of-stock tests. */
    public function outOfStock(): static
    {
        return $this->state(fn () => ['stock' => 0]);
    }

    /** State: exactly one copy left — for concurrency/race-condition tests. */
    public function lastCopy(): static
    {
        return $this->state(fn () => ['stock' => 1]);
    }
}
