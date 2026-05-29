<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\ReservationController as AdminReservationController;
use App\Http\Controllers\ReservationController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// ── Auth helper ───────────────────────────────────────────────────────────────
Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// ── User Routes ───────────────────────────────────────────────────────────────
Route::middleware('auth:sanctum')->group(function (): void {
    // POST /api/reservations — create a new reservation
    Route::post('/reservations', [ReservationController::class, 'store']);
});

// ── Admin Routes ──────────────────────────────────────────────────────────────
// All admin routes require authentication. The policy additionally checks
// that the user has is_admin = true.
Route::middleware('auth:sanctum')->prefix('admin')->name('admin.')->group(function (): void {
    // GET   /api/admin/reservations              — list pending reservations
    Route::get('/reservations', [AdminReservationController::class, 'index'])
         ->name('reservations.index');

    // PATCH /api/admin/reservations/{id}/confirm — confirm a reservation
    Route::patch('/reservations/{reservationId}/confirm', [AdminReservationController::class, 'confirm'])
         ->name('reservations.confirm');

    // PATCH /api/admin/reservations/{id}/cancel  — cancel + restore stock
    Route::patch('/reservations/{reservationId}/cancel', [AdminReservationController::class, 'cancel'])
         ->name('reservations.cancel');
});

