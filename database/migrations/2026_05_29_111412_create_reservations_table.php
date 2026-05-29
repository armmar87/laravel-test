<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Design notes:
     * - expires_at: indexed alongside status so the scheduler can efficiently
     *   find all expired pending reservations in a single query.
     * - confirmed_at / cancelled_at: audit timestamps — we track *when*
     *   transitions happened, not just *that* they happened.
     * - The composite index on (user_id, book_id) speeds up the duplicate
     *   pending check inside CreateReservationAction.
     */
    public function up(): void
    {
        Schema::create('reservations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                  ->constrained()
                  ->cascadeOnDelete();

            $table->foreignId('book_id')
                  ->constrained()
                  ->cascadeOnDelete();

            // Backed enum stored as a plain string for maximum DB compatibility
            $table->string('status')->default('pending');

            // Set to now() + 30 minutes on creation; null after final state
            $table->timestamp('expires_at')->nullable();

            // Audit trail for state transitions
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();

            $table->timestamps();

            // Fast lookup for duplicate-pending check and admin list queries
            $table->index(['user_id', 'book_id']);

            // Scheduler query: WHERE status = 'pending' AND expires_at <= now()
            $table->index(['status', 'expires_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reservations');
    }
};
