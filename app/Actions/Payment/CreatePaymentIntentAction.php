<?php

namespace App\Actions\Payment;

use App\Enums\Booking\BookingStatus;
use App\Enums\Payment\PaymentIntentStatus;
use App\Enums\Payment\PaymentProvider;
use App\Exceptions\ApiException;
use App\Models\Booking\Booking;
use App\Models\Payment\PaymentIntent;
use App\Models\User\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class CreatePaymentIntentAction
{
    public function __invoke(Booking $booking, User $client, array $data = []): PaymentIntent
    {
        return DB::transaction(function () use ($booking, $client, $data) {
            $booking = Booking::query()
                ->with(['payment', 'paymentIntents'])
                ->whereKey($booking->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($booking->client_id !== $client->id) {
                throw new ApiException(
                    error: 'Forbidden',
                    message: 'No puedes pagar esta reserva.',
                    status: Response::HTTP_FORBIDDEN
                );
            }

            if ($booking->status === BookingStatus::Paid || $booking->payment()->exists()) {
                throw new ApiException(
                    error: 'BookingAlreadyPaid',
                    message: 'Esta reserva ya fue pagada.',
                    status: Response::HTTP_CONFLICT
                );
            }

            if ($booking->status !== BookingStatus::Confirmed) {
                throw new ApiException(
                    error: 'BookingNotPayable',
                    message: 'Solo puedes pagar reservas confirmadas.',
                    status: Response::HTTP_CONFLICT
                );
            }

            $existingIntent = $booking->paymentIntents()
                ->whereIn('status', [
                    PaymentIntentStatus::Pending->value,
                    PaymentIntentStatus::Processing->value,
                ])
                ->latest()
                ->first();

            if ($existingIntent instanceof PaymentIntent) {
                if (! $existingIntent->isExpired()) {
                    return $existingIntent->load(['booking', 'payment']);
                }

                $existingIntent->update([
                    'status' => PaymentIntentStatus::Expired,
                ]);
            }

            if ($booking->paymentIntents()
                ->where('status', PaymentIntentStatus::Succeeded->value)
                ->exists()) {
                throw new ApiException(
                    error: 'BookingAlreadyPaid',
                    message: 'Esta reserva ya fue pagada.',
                    status: Response::HTTP_CONFLICT
                );
            }

            $intent = PaymentIntent::create([
                'booking_id' => $booking->id,
                'client_id' => $client->id,
                'professional_id' => $booking->professional_id,
                'provider' => PaymentProvider::Simulator,
                'status' => PaymentIntentStatus::Pending,
                'amount' => (int) round((float) $booking->price_snapshot),
                'currency' => config('proconnect.payments.currency', 'UYU'),
                'provider_reference' => 'sim_'.Str::uuid(),
                'metadata' => $data['metadata'] ?? null,
                'expires_at' => now()->addMinutes(
                    (int) config('proconnect.payments.simulator.intent_expiration_minutes', 30)
                ),
            ]);

            return $intent->load(['booking', 'payment']);
        });
    }
}
