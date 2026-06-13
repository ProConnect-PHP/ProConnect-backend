<?php

namespace App\Actions\Payment;

use App\Actions\Video\EnsureVideoSessionForBookingAction;
use App\DTOs\Payment\ProviderPaymentStatus;
use App\Enums\Booking\BookingStatus;
use App\Enums\Package\ClientPackageStatus;
use App\Enums\Payment\PayableType;
use App\Enums\Payment\PaymentIntentStatus;
use App\Enums\Payment\PaymentStatus;
use App\Events\Package\PackagePurchased;
use App\Events\Payment\PaymentSucceeded;
use App\Exceptions\ApiException;
use App\Models\Booking\Booking;
use App\Models\Package\ClientPackage;
use App\Models\Package\PackageProduct;
use App\Models\Payment\Payment;
use App\Models\Payment\PaymentIntent;
use App\Support\ActivityLog\ActivityLogActorMode;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

final readonly class MarkPaymentSucceededAction
{
    public function __construct(
        private EnsureVideoSessionForBookingAction $ensureVideoSessionForBooking,
    ) {}

    public function __invoke(
        PaymentIntent $paymentIntent,
        ProviderPaymentStatus $providerStatus,
        ActivityLogActorMode $actingAs = ActivityLogActorMode::System,
    ): Payment {
        return DB::transaction(function () use (
            $paymentIntent,
            $providerStatus,
            $actingAs
        ): Payment {
            $intent = PaymentIntent::query()
                ->with(['payment', 'booking', 'packageProduct'])
                ->whereKey($paymentIntent->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($intent->isSucceeded() && $intent->payment instanceof Payment) {
                return $intent->payment->load([
                    'booking.videoSession',
                    'clientPackage.packageProduct',
                    'client',
                    'professional.user',
                ]);
            }

            if (! in_array($intent->status, [
                PaymentIntentStatus::Pending,
                PaymentIntentStatus::CheckoutCreated,
                PaymentIntentStatus::Processing,
                PaymentIntentStatus::Expired,
            ], true)) {
                throw new ApiException(
                    error: 'PaymentIntentNotProcessable',
                    message: 'Este intento de pago no puede procesarse.',
                    status: Response::HTTP_CONFLICT,
                );
            }

            $paidAt = $providerStatus->paidAt
                ? CarbonImmutable::parse($providerStatus->paidAt)
                : now();

            $intent->update([
                'status' => PaymentIntentStatus::Succeeded,
                'processing_at' => $intent->processing_at ?? now(),
                'succeeded_at' => $paidAt,
                'failed_at' => null,
                'cancelled_at' => null,
                'failure_reason' => null,
            ]);

            $payment = Payment::query()->updateOrCreate(
                ['payment_intent_id' => $intent->id],
                [
                    'booking_id' => $intent->booking_id,
                    'package_product_id' => $intent->package_product_id,
                    'client_id' => $intent->client_id,
                    'professional_id' => $intent->professional_id,
                    'provider' => $intent->provider,
                    'status' => PaymentStatus::Succeeded,
                    'amount' => $intent->amount,
                    'currency' => $intent->currency,
                    'provider_reference' => $intent->provider_reference
                        ?: $providerStatus->providerReference,
                    'provider_payment_id' => $providerStatus->providerPaymentId,
                    'raw_provider_status' => $providerStatus->rawStatus,
                    'metadata' => [
                        ...($intent->metadata ?? []),
                        ...$providerStatus->metadata,
                    ],
                    'paid_at' => $paidAt,
                    'failed_at' => null,
                    'failure_reason' => null,
                ]
            );

            $payableType = $intent->payable_type?->value
                ?? $intent->payable_type
                ?? ($intent->booking_id ? PayableType::Booking->value : null);

            if ($payableType === PayableType::Booking->value) {
                $this->finalizeBooking($intent, $payment);
            } elseif ($payableType === PayableType::Package->value) {
                $this->finalizePackage($intent, $payment, $actingAs);
            } else {
                throw new ApiException(
                    error: 'UnsupportedPayableType',
                    message: 'El tipo de pago no es soportado.',
                    status: Response::HTTP_UNPROCESSABLE_ENTITY,
                );
            }

            $payment = $payment->refresh()->load([
                'booking.videoSession',
                'clientPackage.packageProduct.service',
                'client',
                'professional.user',
            ]);

            DB::afterCommit(function () use ($payment, $actingAs): void {
                event(new PaymentSucceeded($payment, $actingAs));
            });

            return $payment;
        });
    }

    private function finalizeBooking(
        PaymentIntent $intent,
        Payment $payment
    ): void {
        $booking = Booking::query()
            ->whereKey($intent->booking_id)
            ->lockForUpdate()
            ->firstOrFail();
        $otherPaymentExists = Payment::query()
            ->where('booking_id', $booking->id)
            ->whereKeyNot($payment->id)
            ->exists();

        if ($otherPaymentExists) {
            throw new ApiException(
                error: 'BookingAlreadyPaid',
                message: 'Esta reserva ya fue pagada.',
                status: Response::HTTP_CONFLICT,
            );
        }

        if (! in_array($booking->status, [
            BookingStatus::Confirmed,
            BookingStatus::Paid,
        ], true)) {
            throw new ApiException(
                error: 'BookingNotPayable',
                message: 'Solo puedes pagar reservas confirmadas.',
                status: Response::HTTP_CONFLICT,
            );
        }

        $booking->update([
            'status' => BookingStatus::Paid,
            'paid_at' => $payment->paid_at,
        ]);

        if (in_array($booking->modality, ['remota', 'hibrida'], true)) {
            ($this->ensureVideoSessionForBooking)($booking);
        }
    }

    private function finalizePackage(
        PaymentIntent $intent,
        Payment $payment,
        ActivityLogActorMode $actingAs
    ): void {
        $packageProduct = PackageProduct::query()
            ->whereKey($intent->package_product_id)
            ->lockForUpdate()
            ->firstOrFail();
        $clientPackage = $payment->client_package_id
            ? ClientPackage::query()->find($payment->client_package_id)
            : null;

        if (! $clientPackage) {
            $clientPackage = ClientPackage::query()
                ->where('package_product_id', $packageProduct->id)
                ->where('client_id', $intent->client_id)
                ->lockForUpdate()
                ->first();
        }

        if (! $clientPackage) {
            $purchasedAt = $payment->paid_at ?? now();
            $clientPackage = ClientPackage::create([
                'package_product_id' => $packageProduct->id,
                'client_id' => $intent->client_id,
                'professional_id' => $intent->professional_id,
                'service_id' => $packageProduct->service_id,
                'status' => ClientPackageStatus::Active,
                'total_sessions' => $packageProduct->sessions_count,
                'used_sessions' => 0,
                'price_snapshot' => $intent->amount,
                'currency' => $intent->currency,
                'purchased_at' => $purchasedAt,
                'expires_at' => $packageProduct->validity_days
                    ? $purchasedAt->copy()->addDays($packageProduct->validity_days)
                    : null,
                'metadata' => [
                    'payment_id' => $payment->id,
                    'payment_intent_id' => $intent->id,
                    'provider' => $intent->provider,
                ],
            ]);

            DB::afterCommit(function () use ($clientPackage, $actingAs): void {
                event(new PackagePurchased($clientPackage->id, $actingAs));
            });
        }

        $payment->update(['client_package_id' => $clientPackage->id]);
    }
}
