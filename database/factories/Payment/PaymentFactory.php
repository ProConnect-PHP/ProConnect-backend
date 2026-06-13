<?php

namespace Database\Factories\Payment;

use App\Enums\Payment\PaymentProvider;
use App\Enums\Payment\PaymentStatus;
use App\Models\Booking\Booking;
use App\Models\Payment\Payment;
use App\Models\Payment\PaymentIntent;
use App\Models\User\ProfessionalProfile;
use App\Models\User\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        return [
            'payment_intent_id' => PaymentIntent::factory()->succeeded(),
            'booking_id' => Booking::factory()->paid(),
            'package_product_id' => null,
            'client_package_id' => null,
            'client_id' => User::factory(),
            'professional_id' => ProfessionalProfile::factory(),
            'provider' => PaymentProvider::Simulator,
            'status' => PaymentStatus::Succeeded,
            'amount' => 1500,
            'currency' => config('proconnect.payments.currency', 'UYU'),
            'provider_reference' => fake()->uuid(),
            'provider_payment_id' => fake()->uuid(),
            'raw_provider_status' => 'approved',
            'metadata' => null,
            'paid_at' => now(),
            'failed_at' => null,
            'refunded_at' => null,
            'failure_reason' => null,
        ];
    }

    public function forPaymentIntent(PaymentIntent $intent): static
    {
        return $this->state(fn () => [
            'payment_intent_id' => $intent->id,
            'booking_id' => $intent->booking_id,
            'package_product_id' => $intent->package_product_id,
            'client_id' => $intent->client_id,
            'professional_id' => $intent->professional_id,
            'provider' => $intent->provider,
            'amount' => $intent->amount,
            'currency' => $intent->currency,
            'provider_reference' => $intent->provider_reference,
            'metadata' => $intent->metadata,
        ]);
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

    public function succeeded(): static
    {
        return $this->state(fn () => [
            'status' => PaymentStatus::Succeeded,
            'paid_at' => now(),
            'failed_at' => null,
            'failure_reason' => null,
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn () => [
            'status' => PaymentStatus::Failed,
            'paid_at' => null,
            'failed_at' => now(),
            'failure_reason' => 'Pago simulado rechazado.',
        ]);
    }

    public function refunded(): static
    {
        return $this->state(fn () => [
            'status' => PaymentStatus::Refunded,
            'refunded_at' => now(),
        ]);
    }

    public function simulator(): static
    {
        return $this->state(fn () => [
            'provider' => PaymentProvider::Simulator,
        ]);
    }
}
