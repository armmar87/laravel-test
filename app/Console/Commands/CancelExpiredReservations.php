<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:cancel-expired-reservations')]
#[Description('Command description')]
class CancelExpiredReservations extends Command
{
    /**
     * Execute the console command.
     */
    public function handle()
    {
        //
    }
}
