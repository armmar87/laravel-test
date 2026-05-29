<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ReservationStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Reservation extends Model
{
    protected $fillable = [
        'user_id',
        'book_id',
        'status',
        'expires_at',
        'confirmed_at',
        'cancelled_at',
    ];

    protected $casts = [
        'status'       => ReservationStatus::class,
        'expires_at'   => 'datetime',
        'confirmed_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    // ─── Relationships ────────────────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }

    // ─── Query Scopes ─────────────────────────────────────────────────────────

    /** @param  Builder<Reservation>  $query */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', ReservationStatus::Pending);
    }

    /**
     * Pending reservations past their expiry window.
     * Used by the auto-cancellation scheduler.
     *
     * @param  Builder<Reservation>  $query
     */
    public function scopeExpired(Builder $query): Builder
    {
        return $query->where('status', ReservationStatus::Pending)
                     ->where('expires_at', '<=', now());
    }

    // ─── Domain helpers ───────────────────────────────────────────────────────

    public function isTransitionable(): bool
    {
        return $this->status->isPending();
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }
}
