<?php

namespace App\Console\Commands\Booking;

use Illuminate\Console\Command;

class SendBookingRemindersCommand extends Command
{
    protected $signature = 'booking:send-reminders {--dry-run}';

    protected $description = 'Deprecated alias for bookings:dispatch-reminders.';

    public function handle(): int
    {
        return $this->call('bookings:dispatch-reminders', [
            '--dry-run' => (bool) $this->option('dry-run'),
        ]);
    }
}
