<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\CreateReservationAction;
use App\DTOs\CreateReservationDTO;
use App\Enums\ReservationStatus;
use App\Events\ReservationCreated;
use App\Exceptions\BookOutOfStockException;
use App\Exceptions\DuplicatePendingReservationException;
use App\Models\Book;
use App\Models\Reservation;
use App\Models\User;
use App\Services\ReservationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ReservationCreationTest extends TestCase
{
    use RefreshDatabase;

    // ─── Action-level tests ───────────────────────────────────────────────────

    #[Test]
    public function it_creates_a_pending_reservation_and_decrements_stock(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create(['stock' => 5]);

        $action      = app(CreateReservationAction::class);
        $reservation = $action->execute(new CreateReservationDTO($user->id, $book->id));

        $this->assertDatabaseHas('reservations', [
            'id'      => $reservation->id,
            'user_id' => $user->id,
            'book_id' => $book->id,
            'status'  => ReservationStatus::Pending->value,
        ]);

        $this->assertNotNull($reservation->expires_at);
        $this->assertTrue($reservation->expires_at->isFuture());
        $this->assertTrue($reservation->expires_at->diffInMinutes(now()) <= 30);

        $this->assertDatabaseHas('books', ['id' => $book->id, 'stock' => 4]);
    }

    #[Test]
    public function it_throws_when_book_is_out_of_stock(): void
    {
        $this->expectException(BookOutOfStockException::class);

        $user = User::factory()->create();
        $book = Book::factory()->outOfStock()->create();

        app(CreateReservationAction::class)
            ->execute(new CreateReservationDTO($user->id, $book->id));
    }

    #[Test]
    public function it_does_not_decrement_stock_when_out_of_stock(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->outOfStock()->create();

        try {
            app(CreateReservationAction::class)
                ->execute(new CreateReservationDTO($user->id, $book->id));
        } catch (BookOutOfStockException) {
            // expected
        }

        $this->assertDatabaseHas('books', ['id' => $book->id, 'stock' => 0]);
        $this->assertDatabaseCount('reservations', 0);
    }

    #[Test]
    public function it_throws_on_duplicate_pending_reservation_for_same_book(): void
    {
        $this->expectException(DuplicatePendingReservationException::class);

        $user = User::factory()->create();
        $book = Book::factory()->create(['stock' => 10]);
        $dto  = new CreateReservationDTO($user->id, $book->id);

        $action = app(CreateReservationAction::class);
        $action->execute($dto);
        $action->execute($dto);
    }

    #[Test]
    public function it_allows_same_user_to_reserve_different_books(): void
    {
        $user  = User::factory()->create();
        $book1 = Book::factory()->create(['stock' => 5]);
        $book2 = Book::factory()->create(['stock' => 5]);

        $action = app(CreateReservationAction::class);
        $action->execute(new CreateReservationDTO($user->id, $book1->id));
        $action->execute(new CreateReservationDTO($user->id, $book2->id));

        $this->assertDatabaseCount('reservations', 2);
    }

    #[Test]
    public function it_allows_different_users_to_reserve_same_book(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $book  = Book::factory()->create(['stock' => 5]);

        $action = app(CreateReservationAction::class);
        $action->execute(new CreateReservationDTO($user1->id, $book->id));
        $action->execute(new CreateReservationDTO($user2->id, $book->id));

        $this->assertDatabaseCount('reservations', 2);
        $this->assertDatabaseHas('books', ['id' => $book->id, 'stock' => 3]);
    }

    #[Test]
    public function it_allows_user_to_re_reserve_after_cancellation(): void
    {
        $user    = User::factory()->create();
        $book    = Book::factory()->create(['stock' => 5]);
        $service = app(ReservationService::class);

        $reservation = $service->reserve(new CreateReservationDTO($user->id, $book->id));
        $service->cancel($reservation->id);

        $newReservation = $service->reserve(new CreateReservationDTO($user->id, $book->id));

        $this->assertEquals(ReservationStatus::Pending, $newReservation->status);
        $this->assertDatabaseCount('reservations', 2);
    }

    // ─── Event dispatch tests ─────────────────────────────────────────────────

    #[Test]
    public function it_dispatches_reservation_created_event_on_success(): void
    {
        Event::fake([ReservationCreated::class]);

        $user = User::factory()->create();
        $book = Book::factory()->create(['stock' => 5]);

        $reservation = app(ReservationService::class)
            ->reserve(new CreateReservationDTO($user->id, $book->id));

        Event::assertDispatched(
            ReservationCreated::class,
            fn (ReservationCreated $e) => $e->reservation->id === $reservation->id
        );
    }

    #[Test]
    public function it_does_not_dispatch_event_when_out_of_stock(): void
    {
        Event::fake([ReservationCreated::class]);

        $user = User::factory()->create();
        $book = Book::factory()->outOfStock()->create();

        try {
            app(ReservationService::class)
                ->reserve(new CreateReservationDTO($user->id, $book->id));
        } catch (BookOutOfStockException) {
        }

        Event::assertNotDispatched(ReservationCreated::class);
    }

    // ─── HTTP layer tests ─────────────────────────────────────────────────────

    #[Test]
    public function authenticated_user_can_create_reservation_via_api(): void
    {
        Event::fake([ReservationCreated::class]);

        $user = User::factory()->create();
        $book = Book::factory()->create(['stock' => 5]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/reservations', ['book_id' => $book->id])
            ->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'reservation' => ['id', 'book_id', 'status', 'expires_at', 'created_at'],
            ])
            ->assertJsonPath('reservation.status', 'pending')
            ->assertJsonPath('reservation.book_id', $book->id);
    }

    #[Test]
    public function unauthenticated_user_cannot_create_reservation(): void
    {
        $book = Book::factory()->create(['stock' => 5]);

        $this->postJson('/api/reservations', ['book_id' => $book->id])
            ->assertStatus(401);
    }

    #[Test]
    public function it_returns_422_when_book_does_not_exist(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/reservations', ['book_id' => 99999])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['book_id']);
    }

    #[Test]
    public function it_returns_422_when_book_id_is_missing(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/reservations', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['book_id']);
    }

    #[Test]
    public function it_returns_422_via_api_when_book_is_out_of_stock(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->outOfStock()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/reservations', ['book_id' => $book->id])
            ->assertStatus(422)
            ->assertJsonPath('message', "Book [{$book->id}] is out of stock.");
    }

    #[Test]
    public function it_returns_409_via_api_on_duplicate_pending_reservation(): void
    {
        Event::fake([ReservationCreated::class]);

        $user = User::factory()->create();
        $book = Book::factory()->create(['stock' => 5]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/reservations', ['book_id' => $book->id])
            ->assertStatus(201);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/reservations', ['book_id' => $book->id])
            ->assertStatus(409);
    }

    // ─── Concurrency simulation tests ─────────────────────────────────────────

    #[Test]
    public function it_prevents_overselling_when_last_copy_is_taken_concurrently(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $book  = Book::factory()->lastCopy()->create(); // stock = 1

        // Request A completes — stock goes to 0
        $action = app(CreateReservationAction::class);
        $action->execute(new CreateReservationDTO($user1->id, $book->id));

        $this->assertDatabaseHas('books', ['id' => $book->id, 'stock' => 0]);

        // Request B must fail
        $this->expectException(BookOutOfStockException::class);
        $action->execute(new CreateReservationDTO($user2->id, $book->id));
    }

    #[Test]
    public function it_prevents_duplicate_when_concurrent_request_just_committed(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create(['stock' => 10]);

        // Simulate Request A already inserted a pending reservation
        Reservation::create([
            'user_id'    => $user->id,
            'book_id'    => $book->id,
            'status'     => ReservationStatus::Pending,
            'expires_at' => now()->addMinutes(30),
        ]);
        $book->decrement('stock');

        // Request B must be rejected by the duplicate check inside the lock
        $this->expectException(DuplicatePendingReservationException::class);
        app(CreateReservationAction::class)
            ->execute(new CreateReservationDTO($user->id, $book->id));
    }
}

