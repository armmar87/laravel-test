<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\Web\AuthController;
use App\Http\Controllers\Admin\Web\ReservationController as AdminWebReservationController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// ── Admin Auth (guest only) ───────────────────────────────────────────────────
Route::middleware('guest')->prefix('admin')->name('admin.web.')->group(function (): void {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.submit');
});

// ── Admin Panel ───────────────────────────────────────────────────────────────
Route::middleware('auth')->prefix('admin')->name('admin.web.')->group(function (): void {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    // Reservations
    Route::get('/reservations', [AdminWebReservationController::class, 'index'])
         ->name('reservations.index');

    Route::patch('/reservations/{reservationId}/confirm', [AdminWebReservationController::class, 'confirm'])
         ->name('reservations.confirm');

    Route::patch('/reservations/{reservationId}/cancel', [AdminWebReservationController::class, 'cancel'])
         ->name('reservations.cancel');
});

