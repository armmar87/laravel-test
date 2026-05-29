<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\CancelExpiredReservationsAction;
use App\DTOs\CreateReservationDTO;
use App\Enums\ReservationStatus;
use App\Events\ReservationCreated;
use App\Models\Book;
use App\Models\Reservation;
use App\Models\User;
use App\Services\ReservationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ReservationExpiryTest extends TestCase
{
    use RefreshDatabase;

    private function reserveBook(User $user, Book $book): Reservation
    {
        Event::fake([ReservationCreated::class]);

        return app(ReservationService::class)
            ->reserve(new CreateReservationDTO($user->id, $book->id));
    }

    private function expireReservation(Reservation $reservation, int $minutesAgo = 31): void
    {
        DB::table('reservations')
            ->where('id', $reservation->id)
            ->update(['expires_at' => now()->subMinutes($minutesAgo)]);
    }

    // ─── Core expiry tests ────────────────────────────────────────────────────

    #[Test]
    public function it_cancels_expired_pending_reservations(): void
    {
        $book        = Book::factory()->create(['stock' => 5]);
        $user        = User::factory()->create();
        $reservation = $this->reserveBook($user, $book);

        $this->expireReservation($reservation);

        $count = app(CancelExpiredReservationsAction::class)->execute();

        $this->assertEquals(1, $count);
        $reservation->refresh();
        $this->assertEquals(ReservationStatus::Cancelled, $reservation->status);
        $this->assertNotNull($reservation->cancelled_at);
        $this->assertNull($reservation->expires_at);
    }

    #[Test]
    public function it_restores_book_stock_when_reservation_expires(): void
    {
        $book        = Book::factory()->create(['stock' => 5]);
        $user        = User::factory()->create();
        $reservation = $this->reserveBook($user, $book);

        $this->assertDatabaseHas('books', ['id' => $book->id, 'stock' => 4]);

        $this->expireReservation($reservation);
        app(CancelExpiredReservationsAction::class)->execute();

        $this->assertDatabaseHas('books', ['id' => $book->id, 'stock' => 5]);
    }

    #[Test]
    public function it_does_not_cancel_non_expired_reservations(): void
    {
        $book              = Book::factory()->create(['stock' => 5]);
        $user              = User::factory()->create();
        $activeReservation = $this->reserveBook($user, $book);

        $count = app(CancelExpiredReservationsAction::class)->execute();

        $this->assertEquals(0, $count);
        $this->assertEquals(ReservationStatus::Pending, $activeReservation->refresh()->status);
        $this->assertDatabaseHas('books', ['id' => $book->id, 'stock' => 4]);
    }

    #[Test]
    public function it_only_cancels_expired_ones_in_a_mixed_set(): void
    {
        $book  = Book::factory()->create(['stock' => 10]);
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $user3 = User::factory()->create();

        $expired1 = $this->reserveBook($user1, $book);
        $expired2 = $this->reserveBook($user2, $book);
        $active   = $this->reserveBook($user3, $book);

        $this->expireReservation($expired1);
        $this->expireReservation($expired2);

        $count = app(CancelExpiredReservationsAction::class)->execute();

        $this->assertEquals(2, $count);
        $this->assertEquals(ReservationStatus::Cancelled, $expired1->refresh()->status);
        $this->assertEquals(ReservationStatus::Cancelled, $expired2->refresh()->status);
        $this->assertEquals(ReservationStatus::Pending,   $active->refresh()->status);
        // Started at 10, 3 reserved = 7, 2 expired & restored = 9
        $this->assertDatabaseHas('books', ['id' => $book->id, 'stock' => 9]);
    }

    #[Test]
    public function it_does_not_cancel_confirmed_reservations_even_if_expires_at_is_past(): void
    {
        $book        = Book::factory()->create(['stock' => 5]);
        $user        = User::factory()->create();
        $reservation = $this->reserveBook($user, $book);

        app(ReservationService::class)->confirm($reservation->id);

        // Manually backdating expires_at to simulate edge-case
        $this->expireReservation($reservation);

        $count = app(CancelExpiredReservationsAction::class)->execute();

        $this->assertEquals(0, $count);
        $this->assertEquals(ReservationStatus::Confirmed, $reservation->refresh()->status);
    }

    #[Test]
    public function it_does_not_cancel_already_cancelled_reservations(): void
    {
        $book        = Book::factory()->create(['stock' => 5]);
        $user        = User::factory()->create();
        $reservation = $this->reserveBook($user, $book);

        app(ReservationService::class)->cancel($reservation->id);
        $this->expireReservation($reservation);

        $count = app(CancelExpiredReservationsAction::class)->execute();

        $this->assertEquals(0, $count);
    }

    // ─── Idempotency ──────────────────────────────────────────────────────────

    #[Test]
    public function running_expiry_action_twice_does_not_double_cancel_or_double_restore_stock(): void
    {
        $book        = Book::factory()->create(['stock' => 5]);
        $user        = User::factory()->create();
        $reservation = $this->reserveBook($user, $book);

        $this->expireReservation($reservation);

        $count1 = app(CancelExpiredReservationsAction::class)->execute();
        $count2 = app(CancelExpiredReservationsAction::class)->execute();

        $this->assertEquals(1, $count1);
        $this->assertEquals(0, $count2);
        $this->assertDatabaseHas('books', ['id' => $book->id, 'stock' => 5]); // restored once
    }

    // ─── Race condition ───────────────────────────────────────────────────────

    #[Test]
    public function scheduler_and_admin_concurrent_cancel_restores_stock_only_once(): void
    {
        $book        = Book::factory()->create(['stock' => 5]);
        $user        = User::factory()->create();
        $reservation = $this->reserveBook($user, $book);

        $this->expireReservation($reservation);
        $this->assertDatabaseHas('books', ['id' => $book->id, 'stock' => 4]);

        $service = app(ReservationService::class);

        // Scheduler runs first
        $count = app(CancelExpiredReservationsAction::class)->execute();
        $this->assertEquals(1, $count);
        $this->assertDatabaseHas('books', ['id' => $book->id, 'stock' => 5]);

        // Admin tries to cancel the same (now terminal) reservation
        $this->expectException(\App\Exceptions\ReservationAlreadyProcessedException::class);
        $service->cancel($reservation->id);
    }

    // ─── Artisan command ──────────────────────────────────────────────────────

    #[Test]
    public function artisan_command_cancels_expired_reservations_and_outputs_count(): void
    {
        $book        = Book::factory()->create(['stock' => 5]);
        $user        = User::factory()->create();
        $reservation = $this->reserveBook($user, $book);

        $this->expireReservation($reservation);

        $this->artisan('reservations:cancel-expired')
            ->assertSuccessful()
            ->expectsOutputToContain('Cancelled 1 expired reservation(s)');
    }

    #[Test]
    public function artisan_command_reports_no_expired_reservations_when_none(): void
    {
        $this->artisan('reservations:cancel-expired')
            ->assertSuccessful()
            ->expectsOutputToContain('No expired reservations found');
    }

    #[Test]
    public function it_processes_large_batches_correctly(): void
    {
        $users = User::factory(15)->create();
        $books = Book::factory(15)->create(['stock' => 5]);

        foreach ($users as $index => $user) {
            Reservation::create([
                'user_id'    => $user->id,
                'book_id'    => $books[$index]->id,
                'status'     => ReservationStatus::Pending,
                'expires_at' => now()->subMinutes(31),
            ]);
            $books[$index]->decrement('stock');
        }

        $count = app(CancelExpiredReservationsAction::class)->execute();

        $this->assertEquals(15, $count);
        $this->assertDatabaseMissing('reservations', ['status' => 'pending']);
    }
}

