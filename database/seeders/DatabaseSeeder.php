<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\ReservationStatus;
use App\Models\Book;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // ── Admin user ────────────────────────────────────────────────────────
        User::factory()->admin()->create([
            'name'  => 'Admin User',
            'email' => 'admin@example.com',
        ]);

        // ── Regular users ─────────────────────────────────────────────────────
        $users = User::factory(5)->create();

        // ── Books ─────────────────────────────────────────────────────────────
        $books = Book::factory(10)->create();

        // One book with only 1 copy — useful for concurrency edge-case testing
        Book::factory()->lastCopy()->create([
            'title'  => 'The Last Copy',
            'author' => 'Test Author',
            'isbn'   => '978-000000001X',
        ]);

        // ── Sample pending reservations ───────────────────────────────────────
        $users->take(3)->each(function (User $user, int $index) use ($books): void {
            Reservation::create([
                'user_id'    => $user->id,
                'book_id'    => $books[$index]->id,
                'status'     => ReservationStatus::Pending,
                'expires_at' => now()->addMinutes(30),
            ]);
            $books[$index]->decrement('stock');
        });

        $this->command->info('✅ Seeded: 1 admin, 5 users, 11 books, 3 pending reservations.');
        $this->command->info('   Admin → email: admin@example.com / password: password');
    }
}
