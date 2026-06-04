<?php

namespace Database\Seeders\Demo;

use App\Enums\Booking\BookingStatus;
use App\Enums\Payment\PaymentIntentStatus;
use App\Enums\Payment\PaymentProvider;
use App\Enums\Payment\PaymentStatus;
use App\Models\Booking\Booking;
use App\Models\Payment\Payment;
use App\Models\Payment\PaymentIntent;
use Illuminate\Database\Seeder;

class DemoPaymentSeeder extends Seeder
{
    public function run(): void
    {
        $currency = config('proconnect.payments.currency', 'UYU');
        $createdPayments = 0;

        Booking::query()
            ->where('status', BookingStatus::Paid->value)
            ->each(function (Booking $booking) use ($currency, &$createdPayments): void {
                $amount = (int) round((float) $booking->price_snapshot);
                $paidAt = $booking->paid_at ?? now()->subDay();

                $intent = PaymentIntent::query()->updateOrCreate(
                    [
                        'booking_id' => $booking->id,
                        'provider_reference' => "demo-paid-{$booking->id}",
                    ],
                    [
                        'client_id' => $booking->client_id,
                        'professional_id' => $booking->professional_id,
                        'provider' => PaymentProvider::Simulator,
                        'status' => PaymentIntentStatus::Succeeded,
                        'amount' => $amount,
                        'currency' => $currency,
                        'metadata' => ['demo' => true],
                        'expires_at' => $paidAt->copy()->addMinutes(30),
                        'processing_at' => $paidAt,
                        'succeeded_at' => $paidAt,
                        'failed_at' => null,
                        'cancelled_at' => null,
                        'failure_reason' => null,
                    ]
                );

                Payment::query()->updateOrCreate(
                    [
                        'booking_id' => $booking->id,
                    ],
                    [
                        'payment_intent_id' => $intent->id,
                        'client_id' => $booking->client_id,
                        'professional_id' => $booking->professional_id,
                        'provider' => PaymentProvider::Simulator,
                        'status' => PaymentStatus::Succeeded,
                        'amount' => $amount,
                        'currency' => $currency,
                        'provider_reference' => $intent->provider_reference,
                        'metadata' => ['demo' => true],
                        'paid_at' => $paidAt,
                        'failed_at' => null,
                        'refunded_at' => null,
                        'failure_reason' => null,
                    ]
                );

                $createdPayments++;
            });

        $confirmedBookings = Booking::query()
            ->where('status', BookingStatus::Confirmed->value)
            ->orderBy('starts_at')
            ->take(4)
            ->get();

        foreach ($confirmedBookings as $index => $booking) {
            $status = $index % 2 === 0
                ? PaymentIntentStatus::Pending
                : PaymentIntentStatus::Failed;

            PaymentIntent::query()->updateOrCreate(
                [
                    'booking_id' => $booking->id,
                    'provider_reference' => "demo-{$status->value}-{$booking->id}",
                ],
                [
                    'client_id' => $booking->client_id,
                    'professional_id' => $booking->professional_id,
                    'provider' => PaymentProvider::Simulator,
                    'status' => $status,
                    'amount' => (int) round((float) $booking->price_snapshot),
                    'currency' => $currency,
                    'metadata' => ['demo' => true],
                    'expires_at' => now()->addMinutes(30),
                    'processing_at' => null,
                    'succeeded_at' => null,
                    'failed_at' => $status === PaymentIntentStatus::Failed ? now()->subHour() : null,
                    'cancelled_at' => null,
                    'failure_reason' => $status === PaymentIntentStatus::Failed
                        ? 'Tarjeta simulada rechazada.'
                        : null,
                ]
            );
        }

        $this->command?->info("Demo payments created/updated ({$createdPayments} succeeded payments)");
    }
}
