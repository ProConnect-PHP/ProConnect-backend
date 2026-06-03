<?php

namespace Database\Seeders\Demo;

use App\Models\User\User;
use App\Models\Service\Service;
use App\Models\Booking\Booking;
use App\Enums\Booking\BookingStatus;
use Illuminate\Database\Seeder;

/**
 * Seeds demo bookings in various states.
 *
 * Creates bookings for all active services distributed across states:
 * - pending (future dates)
 * - confirmed (future dates)
 * - paid (future dates)
 * - in_progress (current/very near future)
 * - completed (past dates)
 * - cancelled (past or future)
 * - no_show (past)
 *
 * Uses snapshots for price and duration to preserve service state at booking time.
 */
class DemoBookingSeeder extends Seeder
{
    /**
     * Run the seeder.
     */
    public function run(): void
    {
        $clients = User::where('role', 'client')->get();
        $services = Service::where('is_active', true)->get();

        if ($clients->isEmpty() || $services->isEmpty()) {
            $this->command?->warn('⚠ No active services or clients found');
            return;
        }

        $clientIndex = 0;
        $bookingCount = 0;

        // Create bookings distributed across all statuses
        foreach ($services as $service) {
            // Get next client (cycle through clients)
            $client = $clients[$clientIndex % $clients->count()];
            $clientIndex++;

            // COMPLETED bookings (past) - 5 bookings
            for ($i = 1; $i <= 5; $i++) {
                $startsAt = now()->subDays(20 - ($i * 3))->setTime(9 + ($i % 8), 0);
                $this->createBooking($client, $service, $startsAt, BookingStatus::Completed, [
                    'completed_at' => $startsAt->copy()->addMinutes($service->duration_minutes),
                    'confirmed_at' => $startsAt->copy()->subDays(2),
                    'paid_at' => $startsAt->copy()->subDay(),
                ]);
                $bookingCount++;
            }

            // PENDING bookings (future) - 3 bookings
            for ($i = 1; $i <= 3; $i++) {
                $startsAt = now()->addDays(2 + ($i * 2))->setTime(10, 0);
                $this->createBooking($client, $service, $startsAt, BookingStatus::Pending);
                $bookingCount++;
            }

            // CONFIRMED bookings (future) - 3 bookings
            for ($i = 1; $i <= 3; $i++) {
                $startsAt = now()->addDays(1)->setTime(14 + $i, 0);
                $this->createBooking($client, $service, $startsAt, BookingStatus::Confirmed, [
                    'confirmed_at' => now()->subDay(),
                ]);
                $bookingCount++;
            }

            // PAID bookings (future) - 2 bookings
            for ($i = 1; $i <= 2; $i++) {
                $startsAt = now()->addDays(1)->setTime(9 + $i, 0);
                $this->createBooking($client, $service, $startsAt, BookingStatus::Paid, [
                    'confirmed_at' => now()->subDays(2),
                    'paid_at' => now()->subDay(),
                ]);
                $bookingCount++;
            }

            // IN_PROGRESS bookings - 1 booking
            $startsAt = now()->setTime(11, 0);
            $this->createBooking($client, $service, $startsAt, BookingStatus::InProgress, [
                'confirmed_at' => now()->subDays(3),
                'paid_at' => now()->subDay(),
            ]);
            $bookingCount++;

            // CANCELLED bookings - 2 bookings (mix of past and future)
            for ($i = 1; $i <= 2; $i++) {
                $startsAt = $i === 1
                    ? now()->subDays(10)->setTime(10, 0)
                    : now()->addDays(7)->setTime(15, 0);
                $this->createBooking($client, $service, $startsAt, BookingStatus::Cancelled, [
                    'cancelled_at' => now()->subDays($i === 1 ? 8 : -1),
                    'cancellation_reason' => $i === 1 ? 'Cliente canceló' : 'Cambio de planes',
                ]);
                $bookingCount++;
            }

            // NO_SHOW bookings - 1 booking (past)
            $startsAt = now()->subDays(3)->setTime(10, 0);
            $this->createBooking($client, $service, $startsAt, BookingStatus::NoShow, [
                'confirmed_at' => $startsAt->copy()->subDays(2),
                'paid_at' => $startsAt->copy()->subDay(),
            ]);
            $bookingCount++;
        }

        $this->command?->info("✓ Demo bookings created/updated ({$bookingCount} total)");
    }

    /**
     * Create or update a booking.
     */
    private function createBooking(
        User $client,
        Service $service,
        \Carbon\Carbon $startsAt,
        BookingStatus $status,
        array $additionalData = []
    ): void {
        $endsAt = $startsAt->copy()->addMinutes($service->duration_minutes);

        Booking::updateOrCreate(
            [
                'service_id' => $service->id,
                'client_id' => $client->id,
                'starts_at' => $startsAt,
            ],
            array_merge([
                'professional_id' => $service->professional_id,
                'ends_at' => $endsAt,
                'status' => $status,
                'modality' => $service->modality,
                'price_snapshot' => $service->price,
                'duration_minutes_snapshot' => $service->duration_minutes,
                'confirmed_at' => null,
                'paid_at' => null,
                'cancelled_at' => null,
                'cancellation_reason' => null,
                'reschedule_reason' => null,
            ], $additionalData)
        );
    }
}
