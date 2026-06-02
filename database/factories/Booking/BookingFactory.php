<?php

namespace Database\Factories\Booking;

use App\Enums\Booking\BookingStatus;
use App\Models\Booking\Booking;
use App\Models\Service\Service;
use App\Models\User\ProfessionalProfile;
use App\Models\User\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Booking>
 */
class BookingFactory extends Factory
{
    protected $model = Booking::class;

    public function definition(): array
    {
        return [
            'service_id' => Service::factory(),
            'professional_id' => ProfessionalProfile::factory(),
            'client_id' => User::factory(),
            'starts_at' => now()->addDays(1)->setTime(9, 0),
            'ends_at' => now()->addDays(1)->setTime(10, 0),
            'status' => BookingStatus::Pending,
            'modality' => 'remota',
            'price_snapshot' => 1500,
            'duration_minutes_snapshot' => 60,
            'confirmed_at' => null,
            'cancelled_at' => null,
            'paid_at' => null,
            'completed_at' => null,
            'no_show_at' => null,
            'cancellation_reason' => null,
            'reschedule_reason' => null,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn () => [
            'status' => BookingStatus::Pending,
            'confirmed_at' => null,
            'cancelled_at' => null,
        ]);
    }

    public function confirmed(): static
    {
        return $this->state(fn () => [
            'status' => BookingStatus::Confirmed,
            'confirmed_at' => now(),
            'cancelled_at' => null,
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn () => [
            'status' => BookingStatus::Cancelled,
            'cancelled_at' => now(),
        ]);
    }

    public function paid(): static
    {
        return $this->state(fn () => [
            'status' => BookingStatus::Paid,
            'paid_at' => now(),
            'cancelled_at' => null,
        ]);
    }
}
