<?php

use App\Exceptions\BookOutOfStockException;
use App\Exceptions\DuplicatePendingReservationException;
use App\Exceptions\ReservationAlreadyProcessedException;
use App\Exceptions\ReservationNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Always return JSON for API routes
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        // Map domain exceptions to clean HTTP JSON responses.
        // Controllers remain free of try/catch blocks.

        $exceptions->render(function (BookOutOfStockException $e, Request $request): JsonResponse {
            return response()->json(['message' => $e->getMessage()], 422);
        });

        $exceptions->render(function (DuplicatePendingReservationException $e, Request $request): JsonResponse {
            return response()->json(['message' => $e->getMessage()], 409);
        });

        $exceptions->render(function (ReservationNotFoundException $e, Request $request): JsonResponse {
            return response()->json(['message' => $e->getMessage()], 404);
        });

        $exceptions->render(function (ReservationAlreadyProcessedException $e, Request $request): JsonResponse {
            return response()->json(['message' => $e->getMessage()], 409);
        });
    })->create();
