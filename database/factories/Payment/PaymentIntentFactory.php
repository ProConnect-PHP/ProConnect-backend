<?php

namespace Database\Factories\Payment;

use App\Enums\Payment\PaymentIntentStatus;
use App\Enums\Payment\PaymentProvider;
use App\Models\Booking\Booking;
use App\Models\Payment\PaymentIntent;
use App\Models\User\ProfessionalProfile;
use App\Models\User\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PaymentIntent>
 */
class PaymentIntentFactory extends Factory
{
    protected $model = PaymentIntent::class;

    public function definition(): array
    {
        return [
            'booking_id' => Booking::factory()->confirmed(),
            'client_id' => User::factory(),
            'professional_id' => ProfessionalProfile::factory(),
            'provider' => PaymentProvider::Simulator,
            'status' => PaymentIntentStatus::Pending,
            'amount' => 1500,
            'currency' => config('proconnect.payments.currency', 'UYU'),
            'provider_reference' => fake()->uuid(),
            'metadata' => null,
            'expires_at' => now()->addMinutes(30),
            'processing_at' => null,
            'succeeded_at' => null,
            'failed_at' => null,
            'cancelled_at' => null,
            'failure_reason' => null,
        ];
    }

    public function forBooking(Booking $booking): static
    {
        return $this->state(fn () => [
            'booking_id' => $booking->id,
            'client_id' => $booking->client_id,
            'professional_id' => $booking->professional_id,
            'amount' => (int) round((float) $booking->price_snapshot),
        ]);
    }

    public function pending(): static
    {
        return $this->state(fn () => [
            'status' => PaymentIntentStatus::Pending,
            'processing_at' => null,
            'succeeded_at' => null,
            'failed_at' => null,
            'cancelled_at' => null,
            'failure_reason' => null,
        ]);
    }

    public function processing(): static
    {
        return $this->state(fn () => [
            'status' => PaymentIntentStatus::Processing,
            'processing_at' => now(),
        ]);
    }

    public function succeeded(): static
    {
        return $this->state(fn () => [
            'status' => PaymentIntentStatus::Succeeded,
            'processing_at' => now(),
            'succeeded_at' => now(),
            'failure_reason' => null,
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn () => [
            'status' => PaymentIntentStatus::Failed,
            'failed_at' => now(),
            'failure_reason' => 'Pago simulado rechazado.',
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn () => [
            'status' => PaymentIntentStatus::Expired,
            'expires_at' => now()->subMinute(),
        ]);
    }

    public function simulator(): static
    {
        return $this->state(fn () => [
            'provider' => PaymentProvider::Simulator,
        ]);
    }
}
