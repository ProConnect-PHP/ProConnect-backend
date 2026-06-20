<?php

namespace App\Actions\Payment;

use App\DTOs\Payment\ProviderPaymentStatus;
use App\Enums\Payment\PaymentIntentStatus;
use App\Enums\Payment\PaymentProvider;
use App\Enums\Payment\PaymentStatus;
use App\Exceptions\ApiException;
use App\Models\Payment\Payment;
use App\Models\Payment\PaymentIntent;
use App\Models\User\User;
use App\Services\Payment\PaymentAmountFormatter;
use App\Services\Payment\PaymentProviderManager;
use App\Services\Payment\Providers\PayPal\PayPalPaymentProvider;
use App\Support\ActivityLog\ActivityLogActorMode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

final readonly class CapturePayPalPaymentIntentAction
{
    public function __construct(
        private PaymentProviderManager $providers,
        private PaymentAmountFormatter $amountFormatter,
        private MarkPaymentSucceededAction $markPaymentSucceeded,
        private MarkPaymentFailedAction $markPaymentFailed,
    ) {}

    public function __invoke(
        PaymentIntent $paymentIntent,
        User $client
    ): PaymentIntent {
        [$intent, $shouldCapture] = DB::transaction(function () use (
            $paymentIntent,
            $client
        ): array {
            $intent = PaymentIntent::query()
                ->whereKey($paymentIntent->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($intent->client_id !== $client->id) {
                throw new ApiException(
                    error: 'Forbidden',
                    message: 'No puedes capturar este pago.',
                    status: Response::HTTP_FORBIDDEN,
                );
            }

            if ($intent->isSucceeded()) {
                return [$intent, false];
            }

            if (in_array($intent->status, [
                PaymentIntentStatus::Failed,
                PaymentIntentStatus::Cancelled,
                PaymentIntentStatus::Expired,
            ], true)) {
                throw new ApiException(
                    error: 'PaymentIntentNotCapturable',
                    message: 'La intencion de pago ya no puede capturarse.',
                    status: Response::HTTP_CONFLICT,
                );
            }

            if ($intent->provider !== PaymentProvider::PayPal) {
                throw new ApiException(
                    error: 'PaymentProviderDoesNotSupportCapture',
                    message: 'Este proveedor no admite captura manual.',
                    status: Response::HTTP_CONFLICT,
                );
            }

            if (! is_string($intent->provider_reference)
                || $intent->provider_reference === '') {
                throw new ApiException(
                    error: 'PayPalOrderIdMissing',
                    message: 'La intencion de pago no tiene una orden PayPal asociada.',
                    status: Response::HTTP_UNPROCESSABLE_ENTITY,
                );
            }

            if (
                $intent->isProcessing()
                && $intent->processing_at?->isAfter(now()->subSeconds(15))
            ) {
                return [$intent, false];
            }

            $intent->update([
                'status' => PaymentIntentStatus::Processing,
                'processing_at' => $intent->processing_at ?? now(),
            ]);

            return [$intent->refresh(), true];
        });

        if (! $shouldCapture) {
            return $this->loadResponseRelations($intent);
        }

        $gateway = $this->providers->driver(PaymentProvider::PayPal);

        if (! $gateway instanceof PayPalPaymentProvider) {
            throw new ApiException(
                error: 'PaymentProviderMisconfigured',
                message: 'El proveedor PayPal no esta configurado correctamente.',
                status: Response::HTTP_SERVICE_UNAVAILABLE,
            );
        }

        Log::info('[PAYPAL CAPTURE REQUEST]', $this->logContext($intent));

        try {
            $providerStatus = $gateway->captureOrder(
                (string) $intent->provider_reference
            );
            $this->validateProviderStatus($intent, $providerStatus);

            Log::info('[PAYPAL CAPTURE RESPONSE]', [
                ...$this->logContext($intent),
                'paypal_capture_id' => $providerStatus->providerPaymentId,
                'provider_status' => $providerStatus->rawStatus,
                'debug_id' => $providerStatus->metadata['debug_id'] ?? null,
            ]);

            match ($providerStatus->status) {
                PaymentStatus::Succeeded => ($this->markPaymentSucceeded)(
                    $intent,
                    $providerStatus,
                    ActivityLogActorMode::Client,
                ),
                PaymentStatus::Rejected,
                PaymentStatus::Failed,
                PaymentStatus::Cancelled => ($this->markPaymentFailed)(
                    $intent,
                    $providerStatus,
                    ActivityLogActorMode::Client,
                ),
                default => $this->markProcessing($intent, $providerStatus),
            };
        } catch (ApiException $exception) {
            $this->markProviderCaptureFailureWhenFinal($intent, $exception);

            throw $exception;
        }

        return $this->loadResponseRelations($intent);
    }

    private function markProcessing(
        PaymentIntent $paymentIntent,
        ProviderPaymentStatus $providerStatus
    ): void {
        DB::transaction(function () use ($paymentIntent, $providerStatus): void {
            $intent = PaymentIntent::query()
                ->whereKey($paymentIntent->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($intent->isSucceeded()) {
                return;
            }

            $intent->update([
                'status' => PaymentIntentStatus::Processing,
                'processing_at' => $intent->processing_at ?? now(),
                'metadata' => [
                    ...($intent->metadata ?? []),
                    ...$providerStatus->metadata,
                    'raw_provider_status' => $providerStatus->rawStatus,
                ],
            ]);
        });
    }

    private function markProviderCaptureFailureWhenFinal(
        PaymentIntent $intent,
        ApiException $exception
    ): void {
        $details = $exception->details();
        $providerStatus = is_array($details)
            ? ($details['provider_status'] ?? null)
            : null;

        if (
            $exception->error() !== 'PayPalCaptureFailed'
            || ! is_int($providerStatus)
            || $providerStatus < 400
            || $providerStatus >= 500
        ) {
            return;
        }

        ($this->markPaymentFailed)(
            $intent,
            new ProviderPaymentStatus(
                providerReference: (string) $intent->provider_reference,
                status: PaymentStatus::Rejected,
                rawStatus: 'CAPTURE_ERROR',
                paymentIntentId: (string) $intent->id,
                metadata: [
                    'paypal_order_id' => $intent->provider_reference,
                    'provider_http_status' => $providerStatus,
                    'provider_error' => $details['provider_error'] ?? null,
                    'debug_id' => data_get($details, 'provider_body.debug_id'),
                ],
            ),
            ActivityLogActorMode::Client,
            $exception->getMessage(),
        );
    }

    private function validateProviderStatus(
        PaymentIntent $intent,
        ProviderPaymentStatus $providerStatus
    ): void {
        if (! hash_equals(
            (string) $intent->provider_reference,
            $providerStatus->providerReference
        )) {
            throw new ApiException(
                error: 'ProviderPaymentIdentityMismatch',
                message: 'La orden devuelta por PayPal no corresponde al pago.',
                status: Response::HTTP_CONFLICT,
            );
        }

        if (
            $providerStatus->paymentIntentId !== null
            && ! hash_equals(
                (string) $intent->id,
                $providerStatus->paymentIntentId
            )
        ) {
            throw new ApiException(
                error: 'ProviderPaymentIntentMismatch',
                message: 'La orden PayPal no corresponde a esta intencion de pago.',
                status: Response::HTTP_CONFLICT,
            );
        }

        if ($providerStatus->status !== PaymentStatus::Succeeded) {
            return;
        }

        $expected = $this->amountFormatter->forProvider(
            $intent,
            PaymentProvider::PayPal
        );

        if (
            $providerStatus->amount === null
            || $providerStatus->currency === null
            || number_format((float) $providerStatus->amount, 2, '.', '')
                !== number_format((float) $expected['amount'], 2, '.', '')
            || strtoupper($providerStatus->currency)
                !== strtoupper($expected['currency'])
        ) {
            throw new ApiException(
                error: 'ProviderPaymentAmountMismatch',
                message: 'El monto confirmado por PayPal no coincide con la intencion.',
                status: Response::HTTP_CONFLICT,
            );
        }

        if ($providerStatus->providerPaymentId === null) {
            throw new ApiException(
                error: 'PayPalCaptureIdMissing',
                message: 'PayPal no devolvio el identificador de captura.',
                status: Response::HTTP_BAD_GATEWAY,
            );
        }

        $alreadyUsed = Payment::query()
            ->where('provider', PaymentProvider::PayPal->value)
            ->where('provider_payment_id', $providerStatus->providerPaymentId)
            ->where('payment_intent_id', '!=', $intent->id)
            ->exists();

        if ($alreadyUsed) {
            throw new ApiException(
                error: 'ProviderPaymentIdentityMismatch',
                message: 'La captura PayPal ya esta asociada a otro pago.',
                status: Response::HTTP_CONFLICT,
            );
        }
    }

    private function loadResponseRelations(
        PaymentIntent $intent
    ): PaymentIntent {
        return $intent->refresh()->load([
            'booking',
            'packageProduct',
            'payment.clientPackage',
        ]);
    }

    private function logContext(PaymentIntent $intent): array
    {
        return [
            'payment_intent_id' => (string) $intent->id,
            'booking_id' => $intent->booking_id,
            'paypal_order_id' => $intent->provider_reference,
            'paypal_capture_id' => null,
            'provider_status' => data_get(
                $intent->metadata,
                'paypal_order_status'
            ),
        ];
    }
}
