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
        $createdPayments = 0;

        Booking::query()
            ->where('status', BookingStatus::Paid->value)
            ->whereNull('client_package_id')
            ->orderBy('starts_at')
            ->each(function (Booking $booking) use (&$createdPayments): void {
                $this->createSucceededPaymentForBooking($booking);
                $createdPayments++;
            });

        $confirmedBookings = Booking::query()
            ->where('status', BookingStatus::Confirmed->value)
            ->whereNull('client_package_id')
            ->whereDoesntHave('payment')
            ->orderBy('starts_at')
            ->take(2)
            ->get();

        if ($confirmedBookings->isNotEmpty()) {
            $this->upsertCheckoutIntent(
                $confirmedBookings->first(),
                PaymentIntentStatus::Pending,
                'demo_pi_pending_' . $confirmedBookings->first()->id,
                'pending_checkout_demo'
            );
        }

        if ($confirmedBookings->count() > 1) {
            $this->upsertCheckoutIntent(
                $confirmedBookings->last(),
                PaymentIntentStatus::Failed,
                'demo_pi_failed_' . $confirmedBookings->last()->id,
                'failed_checkout_demo'
            );
        }

        $this->command?->info("Demo payments created/updated ({$createdPayments} succeeded payments)");
    }

    private function createSucceededPaymentForBooking(Booking $booking): void
    {
        if ($booking->status !== BookingStatus::Paid || $booking->client_package_id !== null) {
            return;
        }

        $paidAt = $booking->paid_at ?? now()->subDay();

        if ($booking->paid_at === null) {
            $booking->update(['paid_at' => $paidAt]);
        }

        $intent = PaymentIntent::query()->updateOrCreate(
            [
                'provider_reference' => 'demo_pi_succeeded_' . $booking->id,
            ],
            [
                'booking_id' => $booking->id,
                'client_id' => $booking->client_id,
                'professional_id' => $booking->professional_id,
                'provider' => PaymentProvider::Simulator,
                'status' => PaymentIntentStatus::Succeeded,
                'amount' => (int) round((float) $booking->price_snapshot),
                'currency' => config('proconnect.payments.currency', 'UYU'),
                'metadata' => [
                    'seeded' => true,
                    'demo_key' => 'succeeded_payment_demo',
                ],
                'expires_at' => $paidAt->copy()->addMinutes(30),
                'processing_at' => $paidAt->copy()->subMinute(),
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
                'amount' => $intent->amount,
                'currency' => $intent->currency,
                'provider_reference' => $intent->provider_reference,
                'metadata' => $intent->metadata,
                'paid_at' => $paidAt,
                'failed_at' => null,
                'refunded_at' => null,
                'failure_reason' => null,
            ]
        );
    }

    private function upsertCheckoutIntent(
        Booking $booking,
        PaymentIntentStatus $status,
        string $providerReference,
        string $demoKey
    ): void {
        if ($booking->client_package_id !== null) {
            return;
        }

        PaymentIntent::query()->updateOrCreate(
            [
                'provider_reference' => $providerReference,
            ],
            [
                'booking_id' => $booking->id,
                'client_id' => $booking->client_id,
                'professional_id' => $booking->professional_id,
                'provider' => PaymentProvider::Simulator,
                'status' => $status,
                'amount' => (int) round((float) $booking->price_snapshot),
                'currency' => config('proconnect.payments.currency', 'UYU'),
                'metadata' => [
                    'seeded' => true,
                    'demo_key' => $demoKey,
                ],
                'expires_at' => $status === PaymentIntentStatus::Pending
                    ? now()->addMinutes(config('proconnect.payments.simulator.intent_expiration_minutes', 30))
                    : null,
                'processing_at' => null,
                'succeeded_at' => null,
                'failed_at' => $status === PaymentIntentStatus::Failed ? now()->subHours(2) : null,
                'cancelled_at' => null,
                'failure_reason' => $status === PaymentIntentStatus::Failed
                    ? 'Tarjeta simulada rechazada.'
                    : null,
            ]
        );
    }
}
