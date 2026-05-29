<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Book extends Model
{
    protected $fillable = [
        'title',
        'author',
        'isbn',
        'stock',
    ];

    protected $casts = [
        'stock' => 'integer',
    ];

    // ─── Relationships ────────────────────────────────────────────────────────

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    // ─── Domain helpers ───────────────────────────────────────────────────────

    /**
     * Convenience check only. Always use lockForUpdate() inside a DB
     * transaction when making reservation decisions to prevent race conditions.
     */
    public function isAvailable(): bool
    {
        return $this->stock > 0;
    }
}
