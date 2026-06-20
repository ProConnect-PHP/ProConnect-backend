<?php

namespace App\Actions\Payment;

use App\DTOs\Payment\ProviderPaymentStatus;
use App\Enums\Payment\PaymentIntentStatus;
use App\Enums\Payment\PaymentStatus;
use App\Events\Payment\PaymentFailed;
use App\Models\Payment\PaymentIntent;
use App\Support\ActivityLog\ActivityLogActorMode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class MarkPaymentFailedAction
{
    public function __invoke(
        PaymentIntent $paymentIntent,
        ProviderPaymentStatus $providerStatus,
        ActivityLogActorMode $actingAs = ActivityLogActorMode::System,
        ?string $reason = null,
    ): PaymentIntent {
        return DB::transaction(function () use (
            $paymentIntent,
            $providerStatus,
            $actingAs,
            $reason
        ): PaymentIntent {
            $intent = PaymentIntent::query()
                ->whereKey($paymentIntent->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($intent->isSucceeded()) {
                return $intent;
            }

            $intentStatus = $providerStatus->status === PaymentStatus::Cancelled
                ? PaymentIntentStatus::Cancelled
                : PaymentIntentStatus::Failed;

            $intent->update([
                'status' => $intentStatus,
                'failed_at' => $intentStatus === PaymentIntentStatus::Failed
                    ? now()
                    : null,
                'cancelled_at' => $intentStatus === PaymentIntentStatus::Cancelled
                    ? now()
                    : null,
                'failure_reason' => $reason
                    ?? 'El proveedor rechazo el pago.',
                'metadata' => [
                    ...($intent->metadata ?? []),
                    ...$providerStatus->metadata,
                    'raw_provider_status' => $providerStatus->rawStatus,
                    'provider_payment_id' => $providerStatus->providerPaymentId,
                ],
            ]);

            $intent = $intent->refresh()->load([
                'booking',
                'packageProduct',
                'payment',
                'client',
            ]);

            DB::afterCommit(function () use ($intent, $actingAs): void {
                event(new PaymentFailed($intent, $actingAs));

                Log::warning('[PAYMENT INTENT FAILED]', [
                    'payment_intent_id' => (string) $intent->id,
                    'booking_id' => $intent->booking_id,
                    'paypal_order_id' => $intent->provider->value === 'paypal'
                        ? $intent->provider_reference
                        : null,
                    'paypal_capture_id' => data_get(
                        $intent->metadata,
                        'paypal_capture_id'
                    ),
                    'provider_status' => $providerStatus->rawStatus,
                ]);
            });

            return $intent;
        });
    }
}
