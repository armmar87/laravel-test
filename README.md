<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

# 📚 Book Reservation API

A RESTful API built with **Laravel 13** for managing book reservations. The system supports user-facing reservation creation and admin-facing reservation management, with strong concurrency safety guarantees.

---

## Table of Contents

- [Overview](#overview)
- [Tech Stack](#tech-stack)
- [Architecture](#architecture)
- [Features](#features)
- [API Endpoints](#api-endpoints)
- [Data Models](#data-models)
- [Reservation Lifecycle](#reservation-lifecycle)
- [Concurrency & Safety](#concurrency--safety)
- [Events & Listeners](#events--listeners)
- [Getting Started](#getting-started)
- [Running Tests](#running-tests)
- [Project Structure](#project-structure)

---

## Overview

This application provides a book reservation system where:

- **Authenticated users** can reserve books from available stock.
- **Admin users** can list pending reservations, confirm them, or cancel them.
- **A scheduler** automatically cancels expired pending reservations and restores stock.

---

## Tech Stack

| Layer        | Technology                           |
|-------------|---------------------------------------|
| Framework    | Laravel 13 (PHP 8.3+)                |
| Auth         | Laravel Sanctum (token-based)        |
| Database     | SQLite (dev) / any PDO-compatible DB |
| Queue        | Laravel Queue (for async listeners)  |
| Testing      | PHPUnit 12                           |
| Code Style   | Laravel Pint                         |

---

## Architecture

The codebase follows a clean, layered architecture:

```
HTTP Request
    │
    ▼
FormRequest (Validation)
    │
    ▼
Controller (thin — delegates everything)
    │
    ▼
ReservationService (orchestration layer)
    │
    ├──► CreateReservationAction (concurrency-safe creation)
    │
    ├──► CancelExpiredReservationsAction (scheduler-driven batch cancel)
    │
    └──► Events → Queued Listeners (side effects, e.g. notifications)
```

**Key design principles:**
- **Controllers** handle HTTP in/out only — no business logic.
- **Services** orchestrate — they coordinate, not implement.
- **Actions** encapsulate a single, focused operation.
- **DTOs** carry immutable, validated data between layers.
- **Policies** centralize all authorization logic.
- **Listeners** handle all side effects (notifications, logging) asynchronously.

---

## Features

- ✅ Token-based authentication via Laravel Sanctum
- ✅ Concurrency-safe reservation creation using `SELECT ... FOR UPDATE` locks
- ✅ Duplicate reservation prevention (one pending reservation per user/book)
- ✅ Admin workflow: list → confirm / cancel reservations
- ✅ Automatic expiry cancellation via scheduler with stock restoration
- ✅ Atomic stock management (no overselling, no double-restore)
- ✅ Async event/listener pipeline for post-creation side effects
- ✅ Policy-based authorization (users vs. admins)
- ✅ Immutable DTOs for safe data flow between layers
- ✅ Chunked batch processing for expired reservations (memory-safe)

---

## API Endpoints

All endpoints require a Sanctum bearer token (`Authorization: Bearer <token>`).

### User Endpoints

| Method | URL                  | Description                          | Auth Required |
|--------|----------------------|--------------------------------------|---------------|
| GET    | `/api/user`          | Get the authenticated user           | ✅ User        |
| POST   | `/api/reservations`  | Create a new reservation             | ✅ User        |

#### POST `/api/reservations`

**Request body:**
```json
{
  "book_id": 1
}
```

**Success response (201):**
```json
{
  "message": "Reservation created successfully.",
  "reservation": {
    "id": 42,
    "book_id": 1,
    "status": "pending",
    "expires_at": "2026-05-30T11:14:12.000000Z",
    "created_at": "2026-05-29T11:14:12.000000Z"
  }
}
```

**Error responses:**
- `422` — Book out of stock (`BookOutOfStockException`)
- `409` — Duplicate pending reservation (`DuplicatePendingReservationException`)
- `403` — Unauthorized

---

### Admin Endpoints

All admin routes are prefixed with `/api/admin` and require `is_admin = true`.

| Method | URL                                          | Description                               |
|--------|----------------------------------------------|-------------------------------------------|
| GET    | `/api/admin/reservations`                    | List all pending reservations (paginated) |
| PATCH  | `/api/admin/reservations/{id}/confirm`       | Confirm a pending reservation             |
| PATCH  | `/api/admin/reservations/{id}/cancel`        | Cancel a reservation & restore stock      |

#### PATCH `/api/admin/reservations/{id}/confirm`

**Success response (200):**
```json
{
  "message": "Reservation confirmed.",
  "reservation": {
    "id": 42,
    "status": "confirmed",
    "confirmed_at": "2026-05-29T12:00:00.000000Z"
  }
}
```

#### PATCH `/api/admin/reservations/{id}/cancel`

**Success response (200):**
```json
{
  "message": "Reservation cancelled and stock restored.",
  "reservation": {
    "id": 42,
    "status": "cancelled",
    "cancelled_at": "2026-05-29T12:00:00.000000Z"
  }
}
```

**Error responses (both confirm & cancel):**
- `404` — Reservation not found
- `409` — Reservation already in a terminal state (confirmed/cancelled)
- `403` — Unauthorized (not admin)

---

## Data Models

### Book

| Column | Type    | Description        |
|--------|---------|--------------------|
| id     | integer | Primary key        |
| title  | string  | Book title         |
| author | string  | Book author        |
| isbn   | string  | ISBN identifier    |
| stock  | integer | Available copies   |

### Reservation

| Column       | Type     | Description                          |
|--------------|----------|--------------------------------------|
| id           | integer  | Primary key                          |
| user_id      | integer  | FK → users                           |
| book_id      | integer  | FK → books                           |
| status       | enum     | `pending`, `confirmed`, `cancelled`  |
| expires_at   | datetime | Auto-expiry deadline (pending only)  |
| confirmed_at | datetime | Timestamp when confirmed             |
| cancelled_at | datetime | Timestamp when cancelled             |

### User

| Column   | Type    | Description                        |
|----------|---------|------------------------------------|
| id       | integer | Primary key                        |
| name     | string  | User name                          |
| email    | string  | Unique email address               |
| is_admin | boolean | Whether the user has admin access  |

---

## Reservation Lifecycle

```
                  ┌─────────┐
   POST /reserve  │         │
  ──────────────► │ PENDING │
                  │         │
                  └────┬────┘
                       │
          ┌────────────┼─────────────┐
          │            │             │
          ▼            ▼             ▼
   Admin confirm  Admin cancel  Scheduler auto-cancel
          │            │         (expires_at reached)
          ▼            ▼             ▼
    ┌──────────┐  ┌───────────┐  ┌───────────────────┐
    │CONFIRMED │  │CANCELLED  │  │CANCELLED           │
    │(terminal)│  │(terminal) │  │+ stock restored    │
    └──────────┘  └───────────┘  └───────────────────┘
```

**Terminal states** (`confirmed`, `cancelled`) cannot transition further.

---

## Concurrency & Safety

This application handles concurrent requests safely using database-level locking.

### Problem: Overselling
Without locking, two simultaneous requests for the last book copy could both read `stock = 1`, both pass the check, and both decrement — resulting in `stock = -1`.

### Solution: `SELECT ... FOR UPDATE`
`CreateReservationAction` acquires a row-level exclusive lock on the book record inside a DB transaction:

```
Request A: BEGIN → LOCK book row → check stock (1) → decrement → COMMIT
Request B: BEGIN → BLOCKED (waiting for A) → reads stock (0) → throws BookOutOfStockException ✅
```

### Problem: Double Stock Restore
When an admin manually cancels a reservation at the same moment the scheduler runs auto-expiry, both could restore the stock — resulting in `stock + 2`.

### Solution: Lock Reservation Row First
Both `ReservationService::cancel()` and `CancelExpiredReservationsAction` use `lockForUpdate()` on the reservation row. Whichever process acquires the lock first cancels it; the second reads `status = cancelled` (terminal) and skips silently.

---

## Events & Listeners

| Event                | Listener                   | Queued | Description                                    |
|----------------------|----------------------------|--------|------------------------------------------------|
| `ReservationCreated` | `HandleReservationCreated` | ✅ Yes  | Post-creation side effects (email, audit, etc.)|

- The event implements `ShouldDispatchAfterCommit` — the listener only fires **after** the DB transaction commits. No listener fires for a rolled-back reservation.
- The listener implements `ShouldQueue` — it runs asynchronously and never blocks the HTTP response.
- Adding new side effects (e.g., Slack notification, analytics) only requires a new listener — zero changes to `ReservationService`.

---

## Getting Started

### Requirements

- PHP 8.3+
- Composer
- Node.js & npm
- A queue worker (for async listeners)

### Installation

```bash
# 1. Clone the repository
git clone <repository-url>
cd laravel-test

# 2. Run the full setup script (install, key gen, migrate, build assets)
composer run setup

# 3. Start all development processes concurrently (server, queue, logs, Vite)
composer run dev
```

The `composer run setup` script automatically handles:
1. `composer install`
2. `.env` file creation from `.env.example`
3. Application key generation (`artisan key:generate`)
4. Database migrations
5. `npm install` + `npm run build`

### Environment

Copy `.env.example` to `.env` and configure as needed:

```env
DB_CONNECTION=sqlite

QUEUE_CONNECTION=database
# Use redis for production workloads
```

---

## Running Tests

```bash
php artisan test
```

With coverage report:

```bash
php artisan test --coverage
```

Test files are located in `tests/Feature/` and `tests/Unit/`.

---

## Project Structure

```
app/
├── Actions/
│   ├── CreateReservationAction.php         # Concurrency-safe reservation creation
│   └── CancelExpiredReservationsAction.php # Batch auto-expiry with stock restore
├── DTOs/
│   └── CreateReservationDTO.php            # Immutable input data transfer object
├── Enums/
│   └── ReservationStatus.php               # pending | confirmed | cancelled
├── Events/
│   └── ReservationCreated.php              # Dispatched after successful reservation
├── Exceptions/
│   ├── BookOutOfStockException.php
│   ├── DuplicatePendingReservationException.php
│   ├── ReservationAlreadyProcessedException.php
│   └── ReservationNotFoundException.php
├── Http/
│   ├── Controllers/
│   │   ├── ReservationController.php       # User-facing endpoints
│   │   └── Admin/ReservationController.php # Admin endpoints
│   └── Requests/
│       ├── StoreReservationRequest.php
│       └── Admin/
├── Listeners/
│   └── HandleReservationCreated.php        # Queued post-creation side effects
├── Models/
│   ├── Book.php
│   ├── Reservation.php
│   └── User.php
├── Policies/
│   └── ReservationPolicy.php               # Authorization: users vs. admins
└── Services/
    └── ReservationService.php              # Orchestration layer
```

---

## License

This project is open-sourced under the [MIT license](https://opensource.org/licenses/MIT).
