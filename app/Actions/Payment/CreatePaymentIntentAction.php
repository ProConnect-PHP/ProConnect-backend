<?php

namespace App\Actions\Payment;

use App\Enums\Booking\BookingStatus;
use App\Enums\Payment\PaymentIntentStatus;
use App\Enums\Payment\PaymentProvider;
use App\Exceptions\ApiException;
use App\Models\Booking\Booking;
use App\Models\Payment\PaymentIntent;
use App\Models\User\User;
use App\Support\ActivityLog\ActivityLogActorMode;
use App\Support\ActivityLog\ActivityLogEvent;
use App\Support\ActivityLog\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class CreatePaymentIntentAction
{
    public function __construct(
        private readonly ActivityLogger $activityLogger,
    ) {}

    public function __invoke(Booking $booking, User $client, array $data = []): PaymentIntent
    {
        $intent = DB::transaction(function () use ($booking, $client, $data) {
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

            if ($booking->client_package_id !== null) {
                throw new ApiException(
                    error: 'BookingNotPayable',
                    message: 'Esta reserva esta cubierta por un paquete.',
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

        if ($intent->wasRecentlyCreated) {
            $this->activityLogger->record(
                event: ActivityLogEvent::PaymentCreated,
                entityType: 'payment_intent',
                entityId: $intent->id,
                entityOwnerId: $intent->professional_id,
                metadata: [
                    'payment_intent_id' => $intent->id,
                    'client_id' => $intent->client_id,
                    'professional_id' => $intent->professional_id,
                    'booking_id' => $intent->booking_id,
                    'amount' => $intent->amount,
                    'currency' => $intent->currency,
                    'provider' => $intent->provider,
                    'new_status' => $intent->status,
                ],
                actor: $client,
                actingAs: ActivityLogActorMode::Client,
            );
        }

        return $intent;
    }
}
