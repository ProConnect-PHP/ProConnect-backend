<?php

namespace App\Actions\Payment;

use App\Actions\Video\EnsureVideoSessionForBookingAction;
use App\Enums\Booking\BookingStatus;
use App\Enums\Payment\PaymentIntentStatus;
use App\Enums\Payment\PaymentStatus;
use App\Exceptions\ApiException;
use App\Events\Payment\PaymentSucceeded;
use App\Models\Booking\Booking;
use App\Models\Payment\Payment;
use App\Models\Payment\PaymentIntent;
use App\Models\User\User;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class SimulatePaymentSuccessAction
{
    public function __construct(
        private readonly EnsureVideoSessionForBookingAction $ensureVideoSessionForBooking
    ) {
    }

    public function __invoke(PaymentIntent $paymentIntent, User $client): Payment
    {
        $result = DB::transaction(function () use ($paymentIntent, $client) {
            $paymentIntent = PaymentIntent::query()
                ->with(['booking', 'payment'])
                ->whereKey($paymentIntent->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($paymentIntent->client_id !== $client->id) {
                throw new ApiException(
                    error: 'Forbidden',
                    message: 'No puedes realizar esta operacion de pago.',
                    status: Response::HTTP_FORBIDDEN
                );
            }

            if ($paymentIntent->isSucceeded()) {
                $payment = $paymentIntent->payment;

                if ($payment instanceof Payment) {
                    $payment->load(['booking']);

                    if (
                        $payment->booking instanceof Booking
                        && in_array($payment->booking->modality, ['remota', 'hibrida'], true)
                    ) {
                        ($this->ensureVideoSessionForBooking)($payment->booking);
                    }

                    return $payment->load(['booking.videoSession']);
                }

                throw new ApiException(
                    error: 'PaymentIntentNotProcessable',
                    message: 'Este intento de pago no puede procesarse.',
                    status: Response::HTTP_CONFLICT
                );
            }

            if ($paymentIntent->isExpired() || $paymentIntent->status === PaymentIntentStatus::Expired) {
                if (in_array($paymentIntent->status, [
                    PaymentIntentStatus::Pending,
                    PaymentIntentStatus::Processing,
                ], true)) {
                    $paymentIntent->update([
                        'status' => PaymentIntentStatus::Expired,
                    ]);
                }

                return PaymentIntentStatus::Expired;
            }

            if (! in_array($paymentIntent->status, [
                PaymentIntentStatus::Pending,
                PaymentIntentStatus::Processing,
            ], true)) {
                throw new ApiException(
                    error: 'PaymentIntentNotProcessable',
                    message: 'Este intento de pago no puede procesarse.',
                    status: Response::HTTP_CONFLICT
                );
            }

            $booking = Booking::query()
                ->whereKey($paymentIntent->booking_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($booking->payment()->exists()) {
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

            $now = now();

            $paymentIntent->update([
                'status' => PaymentIntentStatus::Succeeded,
                'processing_at' => $paymentIntent->processing_at ?? $now,
                'succeeded_at' => $now,
                'failure_reason' => null,
            ]);

            $payment = Payment::create([
                'payment_intent_id' => $paymentIntent->id,
                'booking_id' => $booking->id,
                'client_id' => $paymentIntent->client_id,
                'professional_id' => $paymentIntent->professional_id,
                'provider' => $paymentIntent->provider,
                'status' => PaymentStatus::Succeeded,
                'amount' => $paymentIntent->amount,
                'currency' => $paymentIntent->currency,
                'provider_reference' => $paymentIntent->provider_reference,
                'metadata' => $paymentIntent->metadata,
                'paid_at' => $now,
            ]);

            $booking->update([
                'status' => BookingStatus::Paid,
                'paid_at' => $now,
            ]);

            if (in_array($booking->modality, ['remota', 'hibrida'], true)) {
                ($this->ensureVideoSessionForBooking)($booking);
            }

            $payment = $payment->refresh()->load(['booking.videoSession', 'client', 'professional.user']);

            DB::afterCommit(function () use ($payment): void {
                event(new PaymentSucceeded($payment));
            });

            return $payment;
        });

        if ($result === PaymentIntentStatus::Expired) {
            throw new ApiException(
                error: 'PaymentIntentExpired',
                message: 'El intento de pago expiro.',
                status: Response::HTTP_CONFLICT
            );
        }

        return $result;
    }
}
