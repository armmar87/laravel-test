<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\CancelExpiredReservationsAction;
use Illuminate\Console\Command;

/**
 * Artisan command that triggers auto-cancellation of expired pending reservations.
 *
 * Intentionally thin — all business logic lives in CancelExpiredReservationsAction.
 *
 * Manual run:  php artisan reservations:cancel-expired
 * Scheduled:   every minute via routes/console.php
 */
class CancelExpiredReservations extends Command
{
    protected $signature = 'reservations:cancel-expired';

    protected $description = 'Cancel all pending reservations past their 30-minute expiry window and restore stock.';

    public function handle(CancelExpiredReservationsAction $action): int
    {
        $this->info('Checking for expired reservations...');

        $count = $action->execute();

        if ($count === 0) {
            $this->line('No expired reservations found.');
        } else {
            $this->info("✅ Cancelled {$count} expired reservation(s) and restored stock.");
        }

        return Command::SUCCESS;
    }
}
