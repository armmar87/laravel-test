<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\DTOs\CreateReservationDTO;
use App\Enums\ReservationStatus;
use App\Events\ReservationCreated;
use App\Models\Book;
use App\Models\Reservation;
use App\Models\User;
use App\Services\ReservationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AdminReservationTest extends TestCase
{
    use RefreshDatabase;

    private function createPendingReservation(?User $user = null, ?Book $book = null): Reservation
    {
        Event::fake([ReservationCreated::class]);
        $user ??= User::factory()->create();
        $book ??= Book::factory()->create(['stock' => 5]);

        return app(ReservationService::class)
            ->reserve(new CreateReservationDTO($user->id, $book->id));
    }

    // ─── Index ────────────────────────────────────────────────────────────────

    #[Test]
    public function admin_can_list_pending_reservations(): void
    {
        $admin = User::factory()->admin()->create();
        $this->createPendingReservation();
        $this->createPendingReservation();

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/reservations')
            ->assertStatus(200)
            ->assertJsonStructure(['data', 'total']);
    }

    #[Test]
    public function non_admin_cannot_list_pending_reservations(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/admin/reservations')
            ->assertStatus(403);
    }

    #[Test]
    public function unauthenticated_user_cannot_list_reservations(): void
    {
        $this->getJson('/api/admin/reservations')->assertStatus(401);
    }

    // ─── Confirm ──────────────────────────────────────────────────────────────

    #[Test]
    public function admin_can_confirm_a_pending_reservation(): void
    {
        $admin       = User::factory()->admin()->create();
        $book        = Book::factory()->create(['stock' => 5]);
        $reservation = $this->createPendingReservation(book: $book);

        $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/admin/reservations/{$reservation->id}/confirm")
            ->assertStatus(200)
            ->assertJsonPath('reservation.status', 'confirmed');

        $this->assertDatabaseHas('reservations', [
            'id'     => $reservation->id,
            'status' => ReservationStatus::Confirmed->value,
        ]);
        // Stock stays decremented — confirm does NOT restore stock
        $this->assertDatabaseHas('books', ['id' => $book->id, 'stock' => 4]);
    }

    #[Test]
    public function confirming_sets_confirmed_at_and_clears_expires_at(): void
    {
        $admin       = User::factory()->admin()->create();
        $reservation = $this->createPendingReservation();

        $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/admin/reservations/{$reservation->id}/confirm");

        $reservation->refresh();
        $this->assertNotNull($reservation->confirmed_at);
        $this->assertNull($reservation->expires_at);
    }

    #[Test]
    public function admin_cannot_confirm_already_confirmed_reservation(): void
    {
        $admin       = User::factory()->admin()->create();
        $reservation = $this->createPendingReservation();

        $this->actingAs($admin, 'sanctum')->patchJson("/api/admin/reservations/{$reservation->id}/confirm")->assertStatus(200);
        $this->actingAs($admin, 'sanctum')->patchJson("/api/admin/reservations/{$reservation->id}/confirm")->assertStatus(409);
    }

    #[Test]
    public function admin_cannot_confirm_already_cancelled_reservation(): void
    {
        $admin       = User::factory()->admin()->create();
        $reservation = $this->createPendingReservation();

        $this->actingAs($admin, 'sanctum')->patchJson("/api/admin/reservations/{$reservation->id}/cancel")->assertStatus(200);
        $this->actingAs($admin, 'sanctum')->patchJson("/api/admin/reservations/{$reservation->id}/confirm")->assertStatus(409);
    }

    #[Test]
    public function non_admin_cannot_confirm_a_reservation(): void
    {
        $user        = User::factory()->create();
        $reservation = $this->createPendingReservation();

        $this->actingAs($user, 'sanctum')
            ->patchJson("/api/admin/reservations/{$reservation->id}/confirm")
            ->assertStatus(403);
    }

    #[Test]
    public function confirming_non_existent_reservation_returns_404(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin, 'sanctum')
            ->patchJson('/api/admin/reservations/99999/confirm')
            ->assertStatus(404);
    }

    // ─── Cancel ───────────────────────────────────────────────────────────────

    #[Test]
    public function admin_can_cancel_a_pending_reservation_and_stock_is_restored(): void
    {
        $admin       = User::factory()->admin()->create();
        $book        = Book::factory()->create(['stock' => 5]);
        $reservation = $this->createPendingReservation(book: $book);

        $this->assertDatabaseHas('books', ['id' => $book->id, 'stock' => 4]);

        $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/admin/reservations/{$reservation->id}/cancel")
            ->assertStatus(200)
            ->assertJsonPath('reservation.status', 'cancelled');

        $this->assertDatabaseHas('books', ['id' => $book->id, 'stock' => 5]); // restored
        $this->assertDatabaseHas('reservations', [
            'id'     => $reservation->id,
            'status' => ReservationStatus::Cancelled->value,
        ]);
    }

    #[Test]
    public function cancelling_sets_cancelled_at_and_clears_expires_at(): void
    {
        $admin       = User::factory()->admin()->create();
        $reservation = $this->createPendingReservation();

        $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/admin/reservations/{$reservation->id}/cancel");

        $reservation->refresh();
        $this->assertNotNull($reservation->cancelled_at);
        $this->assertNull($reservation->expires_at);
    }

    #[Test]
    public function admin_cannot_cancel_already_cancelled_reservation(): void
    {
        $admin       = User::factory()->admin()->create();
        $reservation = $this->createPendingReservation();

        $this->actingAs($admin, 'sanctum')->patchJson("/api/admin/reservations/{$reservation->id}/cancel")->assertStatus(200);
        $this->actingAs($admin, 'sanctum')->patchJson("/api/admin/reservations/{$reservation->id}/cancel")->assertStatus(409);
    }

    #[Test]
    public function admin_cannot_cancel_already_confirmed_reservation(): void
    {
        $admin       = User::factory()->admin()->create();
        $reservation = $this->createPendingReservation();

        $this->actingAs($admin, 'sanctum')->patchJson("/api/admin/reservations/{$reservation->id}/confirm")->assertStatus(200);
        $this->actingAs($admin, 'sanctum')->patchJson("/api/admin/reservations/{$reservation->id}/cancel")->assertStatus(409);
    }

    #[Test]
    public function non_admin_cannot_cancel_a_reservation(): void
    {
        $user        = User::factory()->create();
        $reservation = $this->createPendingReservation();

        $this->actingAs($user, 'sanctum')
            ->patchJson("/api/admin/reservations/{$reservation->id}/cancel")
            ->assertStatus(403);
    }

    #[Test]
    public function cancelling_non_existent_reservation_returns_404(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin, 'sanctum')
            ->patchJson('/api/admin/reservations/99999/cancel')
            ->assertStatus(404);
    }

    #[Test]
    public function stock_is_restored_exactly_once_on_concurrent_cancellation(): void
    {
        $book        = Book::factory()->create(['stock' => 5]);
        $reservation = $this->createPendingReservation(book: $book);
        $service     = app(ReservationService::class);

        // First cancel succeeds
        $service->cancel($reservation->id);
        $this->assertDatabaseHas('books', ['id' => $book->id, 'stock' => 5]);

        // Second cancel (simulating concurrent scheduler run) must throw
        try {
            $service->cancel($reservation->id);
        } catch (\App\Exceptions\ReservationAlreadyProcessedException) {
            // expected
        }

        // Stock is still 5 — NOT 6 (no double restore)
        $this->assertDatabaseHas('books', ['id' => $book->id, 'stock' => 5]);
    }
}
