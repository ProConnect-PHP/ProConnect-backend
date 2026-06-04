<?php

namespace App\Actions\Payment;

use App\Enums\Booking\BookingStatus;
use App\Enums\Payment\PaymentIntentStatus;
use App\Exceptions\ApiException;
use App\Events\Payment\PaymentFailed;
use App\Models\Booking\Booking;
use App\Models\Payment\PaymentIntent;
use App\Models\User\User;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class SimulatePaymentFailureAction
{
    public function __invoke(PaymentIntent $paymentIntent, User $client, ?string $reason = null): PaymentIntent
    {
        $result = DB::transaction(function () use ($paymentIntent, $client, $reason) {
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

            if ($paymentIntent->status === PaymentIntentStatus::Failed) {
                return $paymentIntent->load(['booking', 'payment']);
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

            if ($booking->status !== BookingStatus::Confirmed) {
                throw new ApiException(
                    error: 'BookingNotPayable',
                    message: 'Solo puedes pagar reservas confirmadas.',
                    status: Response::HTTP_CONFLICT
                );
            }

            $paymentIntent->update([
                'status' => PaymentIntentStatus::Failed,
                'failed_at' => now(),
                'failure_reason' => $reason ?? 'Pago simulado rechazado.',
            ]);

            $paymentIntent = $paymentIntent->refresh()->load(['booking', 'payment']);

            DB::afterCommit(function () use ($paymentIntent): void {
                event(new PaymentFailed($paymentIntent));
            });

            return $paymentIntent;
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
