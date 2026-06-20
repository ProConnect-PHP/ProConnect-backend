<?php

namespace App\Actions\Payment;

use App\Contracts\Payment\IPaymentProviderGateway;
use App\DTOs\Payment\ProviderPaymentStatus;
use App\DTOs\Payment\ProviderWebhookData;
use App\Enums\Payment\PaymentIntentStatus;
use App\Enums\Payment\PaymentProvider;
use App\Enums\Payment\PaymentStatus;
use App\Enums\Payment\PaymentWebhookEventStatus;
use App\Exceptions\ApiException;
use App\Models\Payment\Payment;
use App\Models\Payment\PaymentIntent;
use App\Models\Payment\PaymentWebhookEvent;
use App\Services\Payment\PaymentAmountFormatter;
use App\Services\Payment\PaymentProviderManager;
use App\Services\Payment\PaymentWebhookIdempotencyService;
use App\Services\Payment\Providers\PayPal\PayPalPaymentProvider;
use App\Support\ActivityLog\ActivityLogActorMode;
use App\Support\ActivityLog\ActivityLogEvent;
use App\Support\ActivityLog\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final readonly class ProcessPaymentWebhookAction
{
    public function __construct(
        private PaymentProviderManager $providers,
        private PaymentAmountFormatter $amountFormatter,
        private PaymentWebhookIdempotencyService $idempotency,
        private MarkPaymentSucceededAction $markPaymentSucceeded,
        private MarkPaymentFailedAction $markPaymentFailed,
        private ActivityLogger $activityLogger,
    ) {}

    public function __invoke(
        PaymentProvider $provider,
        Request $request
    ): PaymentWebhookEvent {
        $gateway = $this->providers->driver($provider);
        $webhook = $gateway->parseWebhook($request);
        [$event, $acquired] = $this->idempotency->acquire($webhook);

        if ($provider === PaymentProvider::PayPal) {
            Log::info('[PAYPAL WEBHOOK RECEIVED]', [
                ...$this->webhookMetadata($event),
                'payment_intent_id' => data_get(
                    $event->payload,
                    'resource.purchase_units.0.custom_id'
                ) ?? data_get($event->payload, 'resource.custom_id'),
                'booking_id' => null,
                'paypal_order_id' => $event->resource_id,
                'paypal_capture_id' => data_get($event->payload, 'resource.id'),
                'transmission_id' => $request->header('PAYPAL-TRANSMISSION-ID'),
            ]);
        }

        $this->activityLogger->record(
            event: ActivityLogEvent::PaymentWebhookReceived,
            entityType: 'payment_webhook_event',
            entityId: $event->id,
            metadata: $this->webhookMetadata($event),
            actingAs: ActivityLogActorMode::System,
        );

        if (! $acquired) {
            $this->activityLogger->record(
                event: ActivityLogEvent::PaymentWebhookDuplicated,
                entityType: 'payment_webhook_event',
                entityId: $event->id,
                metadata: $this->webhookMetadata($event),
                actingAs: ActivityLogActorMode::System,
            );

            return $event;
        }

        if (
            $webhook->provider === PaymentProvider::MercadoPago
            && strtolower(trim((string) $webhook->resourceType)) !== 'payment'
        ) {
            $event->update([
                'status' => PaymentWebhookEventStatus::Ignored,
                'failure_reason' => 'Unsupported MercadoPago webhook resource type.',
                'processed_at' => now(),
            ]);

            return $event->refresh();
        }

        if (
            $webhook->provider === PaymentProvider::MercadoPago
            && (
                $webhook->resourceId === null
                || ! ctype_digit($webhook->resourceId)
            )
        ) {
            $event->update([
                'status' => PaymentWebhookEventStatus::Ignored,
                'failure_reason' => 'Invalid MercadoPago payment id.',
                'processed_at' => now(),
            ]);

            return $event->refresh();
        }

        if (! $webhook->signatureValid) {
            $this->recordInvalidSignatureActivity($event);

            if ($webhook->provider !== PaymentProvider::MercadoPago) {
                return $this->markInvalidSignature($event);
            }

            return $this->processMercadoPagoInvalidSignatureWebhook(
                webhook: $webhook,
                event: $event,
                gateway: $gateway,
            );
        }

        $this->activityLogger->record(
            event: ActivityLogEvent::PaymentWebhookSignatureValid,
            entityType: 'payment_webhook_event',
            entityId: $event->id,
            metadata: $this->webhookMetadata($event),
            actingAs: ActivityLogActorMode::System,
        );

        return $this->processSignedWebhook(
            provider: $provider,
            webhook: $webhook,
            event: $event,
            gateway: $gateway,
        );
    }

    private function processSignedWebhook(
        PaymentProvider $provider,
        ProviderWebhookData $webhook,
        PaymentWebhookEvent $event,
        IPaymentProviderGateway $gateway,
    ): PaymentWebhookEvent {
        if ($provider === PaymentProvider::PayPal) {
            return $this->processPayPalSignedWebhook(
                webhook: $webhook,
                event: $event,
                gateway: $gateway,
            );
        }

        try {
            if (! $webhook->resourceId) {
                throw new ApiException(
                    error: 'PaymentWebhookResourceMissing',
                    message: 'El webhook no contiene una referencia de pago.',
                    status: Response::HTTP_UNPROCESSABLE_ENTITY,
                );
            }

            $event->update(['status' => PaymentWebhookEventStatus::Processing]);

            $providerStatus = $gateway->fetchPaymentStatus(
                $webhook->resourceId
            );
            $intent = $this->resolveIntent(
                provider: $provider,
                paymentIntentId: $providerStatus->paymentIntentId,
                providerReference: $providerStatus->providerReference,
                resourceId: $webhook->resourceId,
            );
            $event->update(['payment_intent_id' => $intent->id]);
            $this->validateProviderAmount($intent, $providerStatus);
            $this->validateProviderPaymentIdentity(
                $intent,
                $providerStatus
            );
            $providerStatus = $this->withWebhookAuditMetadata(
                providerStatus: $providerStatus,
                event: $event,
                signatureValid: true,
                verificationMode: 'signed_webhook',
            );

            return $this->applyProviderPaymentStatus(
                intent: $intent,
                providerStatus: $providerStatus,
                event: $event,
                processedReason: null,
            );
        } catch (Throwable $exception) {
            $this->markFailed($event, $exception);

            throw $exception;
        }
    }

    private function processPayPalSignedWebhook(
        ProviderWebhookData $webhook,
        PaymentWebhookEvent $event,
        IPaymentProviderGateway $gateway,
    ): PaymentWebhookEvent {
        if (! $gateway instanceof PayPalPaymentProvider) {
            throw new ApiException(
                error: 'PaymentProviderMisconfigured',
                message: 'El proveedor PayPal no esta configurado correctamente.',
                status: Response::HTTP_SERVICE_UNAVAILABLE,
            );
        }

        $eventType = strtoupper(trim((string) $webhook->eventType));
        $supportedEventTypes = [
            'CHECKOUT.ORDER.APPROVED',
            'PAYMENT.CAPTURE.COMPLETED',
            'PAYMENT.CAPTURE.PENDING',
            'PAYMENT.CAPTURE.DENIED',
        ];
        $intent = null;

        if (! in_array($eventType, $supportedEventTypes, true)) {
            $event->update([
                'status' => PaymentWebhookEventStatus::Ignored,
                'failure_reason' => 'Unsupported PayPal webhook event type.',
                'processed_at' => now(),
            ]);

            return $event->refresh();
        }

        try {
            if (! $webhook->resourceId) {
                throw new ApiException(
                    error: 'PaymentWebhookResourceMissing',
                    message: 'El webhook de PayPal no contiene una orden asociada.',
                    status: Response::HTTP_UNPROCESSABLE_ENTITY,
                );
            }

            $event->update(['status' => PaymentWebhookEventStatus::Processing]);
            $webhookStatus = $gateway->statusFromWebhook($webhook);
            $intent = $this->resolveIntent(
                provider: PaymentProvider::PayPal,
                paymentIntentId: $webhookStatus->paymentIntentId,
                providerReference: $webhookStatus->providerReference,
                resourceId: $webhook->resourceId,
            );
            $event->update(['payment_intent_id' => $intent->id]);
            $this->validatePayPalOrderIdentity($intent, $webhookStatus);

            if ($eventType === 'CHECKOUT.ORDER.APPROVED') {
                Log::info('[PAYPAL CAPTURE REQUEST]', [
                    'payment_intent_id' => (string) $intent->id,
                    'booking_id' => $intent->booking_id,
                    'paypal_order_id' => $webhookStatus->providerReference,
                    'paypal_capture_id' => null,
                    'event_type' => $eventType,
                    'provider_status' => $webhookStatus->rawStatus,
                ]);

                $providerStatus = $gateway->captureOrder(
                    $webhookStatus->providerReference
                );

                Log::info('[PAYPAL CAPTURE RESPONSE]', [
                    'payment_intent_id' => (string) $intent->id,
                    'booking_id' => $intent->booking_id,
                    'paypal_order_id' => $providerStatus->providerReference,
                    'paypal_capture_id' => $providerStatus->providerPaymentId,
                    'event_type' => $eventType,
                    'provider_status' => $providerStatus->rawStatus,
                    'debug_id' => $providerStatus->metadata['debug_id'] ?? null,
                ]);
            } else {
                $providerStatus = $webhookStatus;
            }

            if (
                $providerStatus->status === PaymentStatus::Succeeded
                && ($providerStatus->amount === null
                    || $providerStatus->currency === null)
            ) {
                $providerStatus = $gateway->fetchOrderStatus(
                    $webhookStatus->providerReference
                );
            }

            $this->validatePayPalOrderIdentity($intent, $providerStatus);
            $this->validateProviderAmount($intent, $providerStatus);
            $this->validateProviderPaymentIdentity($intent, $providerStatus);
            $providerStatus = $this->withWebhookAuditMetadata(
                providerStatus: $providerStatus,
                event: $event,
                signatureValid: true,
                verificationMode: $eventType === 'CHECKOUT.ORDER.APPROVED'
                    ? 'signed_webhook_capture'
                    : 'signed_webhook_capture_event',
            );

            return $this->applyProviderPaymentStatus(
                intent: $intent,
                providerStatus: $providerStatus,
                event: $event,
                processedReason: null,
            );
        } catch (Throwable $exception) {
            if (
                $intent instanceof PaymentIntent
                && $eventType === 'CHECKOUT.ORDER.APPROVED'
                && $exception instanceof ApiException
                && $this->isFinalPayPalCaptureFailure($exception)
            ) {
                return $this->markPayPalCaptureFailure(
                    event: $event,
                    intent: $intent,
                    exception: $exception,
                );
            }

            $this->markFailed($event, $exception);

            throw $exception;
        }
    }

    private function isFinalPayPalCaptureFailure(
        ApiException $exception
    ): bool {
        $providerStatus = is_array($exception->details())
            ? ($exception->details()['provider_status'] ?? null)
            : null;

        return $exception->error() === 'PayPalCaptureFailed'
            && is_int($providerStatus)
            && $providerStatus >= 400
            && $providerStatus < 500;
    }

    private function markPayPalCaptureFailure(
        PaymentWebhookEvent $event,
        PaymentIntent $intent,
        ApiException $exception,
    ): PaymentWebhookEvent {
        $details = is_array($exception->details())
            ? $exception->details()
            : [];
        $providerStatus = new ProviderPaymentStatus(
            providerReference: (string) $intent->provider_reference,
            status: PaymentStatus::Rejected,
            rawStatus: 'CAPTURE_ERROR',
            paymentIntentId: (string) $intent->id,
            metadata: [
                'paypal_order_id' => $intent->provider_reference,
                'provider_http_status' => $details['provider_status'] ?? null,
                'provider_error' => $details['provider_error'] ?? null,
                'debug_id' => data_get($details, 'provider_body.debug_id'),
                'paypal_event_type' => $event->event_type,
            ],
        );

        ($this->markPaymentFailed)(
            $intent,
            $providerStatus,
            ActivityLogActorMode::System,
            $exception->getMessage(),
        );

        $event->update([
            'status' => PaymentWebhookEventStatus::Processed,
            'processed_at' => now(),
            'failure_reason' => 'PayPal capture rejected: '
                .mb_substr($exception->getMessage(), 0, 900),
        ]);

        Log::warning('[PAYPAL WEBHOOK PROCESSED]', [
            ...$this->webhookMetadata($event),
            'payment_intent_id' => (string) $intent->id,
            'booking_id' => $intent->booking_id,
            'paypal_order_id' => $intent->provider_reference,
            'paypal_capture_id' => null,
            'provider_status' => 'CAPTURE_ERROR',
            'debug_id' => data_get($details, 'provider_body.debug_id'),
        ]);

        return $event->refresh();
    }

    private function processMercadoPagoInvalidSignatureWebhook(
        ProviderWebhookData $webhook,
        PaymentWebhookEvent $event,
        IPaymentProviderGateway $gateway,
    ): PaymentWebhookEvent {
        try {
            $providerStatus = $gateway->fetchPaymentStatus(
                (string) $webhook->resourceId
            );
        } catch (Throwable $exception) {
            return $this->markInvalidSignature(
                event: $event,
                reason: 'Invalid signature and MercadoPago payment fetch failed: '
                    .$exception->getMessage(),
            );
        }

        $intent = $this->findMercadoPagoIntentFromProviderStatus(
            $providerStatus
        );

        if (! $intent) {
            return $this->markInvalidSignature(
                event: $event,
                reason: 'Invalid signature and no matching PaymentIntent from provider response.',
            );
        }

        if (! $this->canTrustMercadoPagoProviderLookup(
            providerStatus: $providerStatus,
            intent: $intent,
            webhookResourceId: (string) $webhook->resourceId,
        )) {
            return $this->markInvalidSignature(
                event: $event,
                reason: 'Invalid signature and MercadoPago provider verification mismatch.',
            );
        }

        $processedReason = 'Signature invalid, but payment verified through MercadoPago API.';
        $providerStatus = $this->withWebhookAuditMetadata(
            providerStatus: $providerStatus,
            event: $event,
            signatureValid: false,
            verificationMode: 'provider_verified_after_invalid_signature',
        );

        $event->update([
            'status' => PaymentWebhookEventStatus::Processing,
            'payment_intent_id' => $intent->id,
            'failure_reason' => $processedReason,
        ]);

        Log::warning(
            'MercadoPago webhook processed through provider verification fallback',
            [
                'webhook_event_id' => (string) $event->id,
                'resource_id' => $webhook->resourceId,
                'payment_intent_id' => (string) $intent->id,
                'provider_payment_id' => $providerStatus->providerPaymentId,
                'raw_status' => $providerStatus->rawStatus,
            ]
        );

        try {
            return $this->applyProviderPaymentStatus(
                intent: $intent,
                providerStatus: $providerStatus,
                event: $event,
                processedReason: $processedReason,
            );
        } catch (Throwable $exception) {
            $this->markFailed($event, $exception);

            throw $exception;
        }
    }

    private function findMercadoPagoIntentFromProviderStatus(
        ProviderPaymentStatus $providerStatus
    ): ?PaymentIntent {
        if ($providerStatus->paymentIntentId === null) {
            return null;
        }

        return PaymentIntent::query()
            ->with('payment')
            ->whereKey($providerStatus->paymentIntentId)
            ->where('provider', PaymentProvider::MercadoPago->value)
            ->first();
    }

    private function canTrustMercadoPagoProviderLookup(
        ProviderPaymentStatus $providerStatus,
        PaymentIntent $intent,
        string $webhookResourceId,
    ): bool {
        if (
            $providerStatus->providerPaymentId === null
            || ! hash_equals(
                $webhookResourceId,
                $providerStatus->providerPaymentId
            )
            || $providerStatus->paymentIntentId === null
            || ! hash_equals(
                (string) $intent->id,
                $providerStatus->paymentIntentId
            )
            || $intent->provider !== PaymentProvider::MercadoPago
            || $providerStatus->amount === null
            || $providerStatus->currency === null
        ) {
            return false;
        }

        $expected = $this->amountFormatter->forProvider(
            $intent,
            PaymentProvider::MercadoPago
        );

        if (
            ! $this->sameMoney($providerStatus->amount, $expected['amount'])
            || strtoupper($providerStatus->currency)
                !== strtoupper($expected['currency'])
        ) {
            return false;
        }

        return $this->providerPaymentIdentityMatches(
            $intent,
            $providerStatus
        );
    }

    private function applyProviderPaymentStatus(
        PaymentIntent $intent,
        ProviderPaymentStatus $providerStatus,
        PaymentWebhookEvent $event,
        ?string $processedReason,
    ): PaymentWebhookEvent {
        match ($providerStatus->status) {
            PaymentStatus::Succeeded,
            PaymentStatus::Approved => ($this->markPaymentSucceeded)(
                $intent,
                $providerStatus,
                ActivityLogActorMode::System,
            ),
            PaymentStatus::Failed,
            PaymentStatus::Rejected,
            PaymentStatus::Cancelled => ($this->markPaymentFailed)(
                $intent,
                $providerStatus,
                ActivityLogActorMode::System,
            ),

            default => $this->markProcessing($intent, $providerStatus),
        };

        $event->update([
            'status' => PaymentWebhookEventStatus::Processed,
            'processed_at' => now(),
            'failure_reason' => $processedReason,
        ]);

        $this->activityLogger->record(
            event: ActivityLogEvent::PaymentWebhookProcessed,
            entityType: 'payment_webhook_event',
            entityId: $event->id,
            entityOwnerId: $intent->professional_id,
            metadata: [
                ...$this->webhookMetadata($event),
                ...$this->intentMetadata($intent, $providerStatus->rawStatus),
                'verification_mode' => $providerStatus
                    ->metadata['verification_mode'] ?? null,
            ],
            actingAs: ActivityLogActorMode::System,
        );

        if ($event->provider === PaymentProvider::PayPal) {
            Log::info('[PAYPAL WEBHOOK PROCESSED]', [
                ...$this->webhookMetadata($event),
                'payment_intent_id' => (string) $intent->id,
                'booking_id' => $intent->booking_id,
                'paypal_order_id' => $providerStatus->providerReference,
                'paypal_capture_id' => $providerStatus->providerPaymentId,
                'provider_status' => $providerStatus->rawStatus,
                'debug_id' => $providerStatus->metadata['debug_id'] ?? null,
            ]);
        }

        return $event->refresh();
    }

    private function validatePayPalOrderIdentity(
        PaymentIntent $intent,
        ProviderPaymentStatus $providerStatus
    ): void {
        if (
            ! is_string($intent->provider_reference)
            || $intent->provider_reference === ''
            || ! hash_equals(
                $intent->provider_reference,
                $providerStatus->providerReference
            )
        ) {
            throw new ApiException(
                error: 'ProviderPaymentIdentityMismatch',
                message: 'La orden PayPal no corresponde a la intencion de pago.',
                status: Response::HTTP_CONFLICT,
            );
        }
    }

    private function resolveIntent(
        PaymentProvider $provider,
        ?string $paymentIntentId,
        string $providerReference,
        string $resourceId,
    ): PaymentIntent {
        $intent = $paymentIntentId
            ? PaymentIntent::query()
                ->whereKey($paymentIntentId)
                ->where('provider', $provider->value)
                ->first()
            : null;

        $intent ??= PaymentIntent::query()
            ->where('provider', $provider->value)
            ->where(function ($query) use ($providerReference, $resourceId): void {
                $query->where('provider_reference', $providerReference)
                    ->orWhere('provider_reference', $resourceId);
            })
            ->first();

        if (! $intent) {
            throw new ApiException(
                error: 'PaymentIntentNotFound',
                message: 'No se encontro la intencion de pago del webhook.',
                status: Response::HTTP_NOT_FOUND,
            );
        }

        return $intent;
    }

    private function markProcessing(
        PaymentIntent $intent,
        ProviderPaymentStatus $providerStatus
    ): void {
        DB::transaction(function () use ($intent, $providerStatus): void {
            $lockedIntent = PaymentIntent::query()
                ->whereKey($intent->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedIntent->isSucceeded()) {
                return;
            }

            $lockedIntent->update([
                'status' => PaymentIntentStatus::Processing,
                'processing_at' => $lockedIntent->processing_at ?? now(),
                'metadata' => [
                    ...($lockedIntent->metadata ?? []),
                    ...$providerStatus->metadata,
                    'raw_provider_status' => $providerStatus->rawStatus,
                ],
            ]);
        });
    }

    private function validateProviderAmount(
        PaymentIntent $intent,
        ProviderPaymentStatus $providerStatus
    ): void {
        if (! in_array($providerStatus->status, [
            PaymentStatus::Succeeded,
            PaymentStatus::Approved
        ], true)) {
            return;
        }

        if ($providerStatus->amount === null || $providerStatus->currency === null) {
            throw new ApiException(
                error: 'ProviderPaymentAmountMissing',
                message: 'El proveedor no devolvio monto y moneda verificables.',
                status: Response::HTTP_BAD_GATEWAY,
            );
        }

        $expected = $this->amountFormatter->forProvider(
            $intent,
            $intent->provider
        );
        $amountMatches = $this->sameMoney(
            $providerStatus->amount,
            $expected['amount']
        );
        $currencyMatches = strtoupper($providerStatus->currency)
            === strtoupper($expected['currency']);

        if (! $amountMatches || ! $currencyMatches) {
            throw new ApiException(
                error: 'ProviderPaymentAmountMismatch',
                message: 'El monto confirmado por el proveedor no coincide con la intencion.',
                status: Response::HTTP_CONFLICT,
                details: [
                    'expected_amount' => $expected['amount'],
                    'expected_currency' => $expected['currency'],
                ],
            );
        }
    }

    private function validateProviderPaymentIdentity(
        PaymentIntent $intent,
        ProviderPaymentStatus $providerStatus,
    ): void {
        if (! $this->providerPaymentIdentityMatches($intent, $providerStatus)) {
            throw new ApiException(
                error: 'ProviderPaymentIdentityMismatch',
                message: 'El pago del proveedor ya esta asociado a otra intencion.',
                status: Response::HTTP_CONFLICT,
            );
        }
    }

    private function providerPaymentIdentityMatches(
        PaymentIntent $intent,
        ProviderPaymentStatus $providerStatus,
    ): bool {
        $providerPaymentId = $providerStatus->providerPaymentId;

        if ($providerPaymentId === null) {
            return ! $intent->isSucceeded();
        }

        $intentPayment = $intent->relationLoaded('payment')
            ? $intent->payment
            : Payment::query()
                ->where('payment_intent_id', $intent->id)
                ->first();

        if (
            $intentPayment
            && ! hash_equals(
                (string) $intentPayment->provider_payment_id,
                $providerPaymentId
            )
        ) {
            return false;
        }

        if ($intent->isSucceeded() && ! $intentPayment) {
            return false;
        }

        return ! Payment::query()
            ->where('provider', $intent->provider->value)
            ->where('provider_payment_id', $providerPaymentId)
            ->where('payment_intent_id', '!=', $intent->id)
            ->exists();
    }

    private function sameMoney(
        int|float|string $left,
        int|float|string $right
    ): bool {
        return number_format((float) $left, 2, '.', '')
            === number_format((float) $right, 2, '.', '');
    }

    private function withWebhookAuditMetadata(
        ProviderPaymentStatus $providerStatus,
        PaymentWebhookEvent $event,
        bool $signatureValid,
        string $verificationMode,
    ): ProviderPaymentStatus {
        return new ProviderPaymentStatus(
            providerReference: $providerStatus->providerReference,
            status: $providerStatus->status,
            rawStatus: $providerStatus->rawStatus,
            providerPaymentId: $providerStatus->providerPaymentId,
            paymentIntentId: $providerStatus->paymentIntentId,
            paidAt: $providerStatus->paidAt,
            amount: $providerStatus->amount,
            currency: $providerStatus->currency,
            metadata: [
                ...$providerStatus->metadata,
                'webhook_signature_valid' => $signatureValid,
                'verification_mode' => $verificationMode,
                'webhook_event_id' => (string) $event->id,
            ],
        );
    }

    private function markInvalidSignature(
        PaymentWebhookEvent $event,
        string $reason = 'Invalid webhook signature.',
    ): PaymentWebhookEvent {
        $event->update([
            'status' => PaymentWebhookEventStatus::InvalidSignature,
            'failure_reason' => mb_substr($reason, 0, 1000),
            'processed_at' => now(),
        ]);

        return $event->refresh();
    }

    private function recordInvalidSignatureActivity(
        PaymentWebhookEvent $event
    ): void {
        $this->activityLogger->record(
            event: ActivityLogEvent::PaymentWebhookSignatureInvalid,
            entityType: 'payment_webhook_event',
            entityId: $event->id,
            severity: 'warning',
            statusCode: Response::HTTP_UNAUTHORIZED,
            metadata: $this->webhookMetadata($event),
            actingAs: ActivityLogActorMode::System,
        );
    }

    private function markFailed(
        PaymentWebhookEvent $event,
        Throwable $exception
    ): void {
        $event->update([
            'status' => PaymentWebhookEventStatus::Failed,
            'failure_reason' => mb_substr($exception->getMessage(), 0, 1000),
            'processed_at' => now(),
        ]);
    }

    private function webhookMetadata(PaymentWebhookEvent $event): array
    {
        return [
            'provider' => $event->provider,
            'provider_event_id' => $event->provider_event_id,
            'event_type' => $event->event_type,
            'resource_type' => $event->resource_type,
            'resource_id' => $event->resource_id,
            'signature_valid' => $event->signature_valid,
            'custom_id' => data_get(
                $event->payload,
                'resource.purchase_units.0.custom_id'
            ) ?? data_get($event->payload, 'resource.custom_id'),
        ];
    }

    private function intentMetadata(
        PaymentIntent $intent,
        ?string $rawStatus = null,
        ?Payment $payment = null,
    ): array {
        return [
            'provider' => $intent->provider,
            'payment_intent_id' => $intent->id,
            'payment_id' => $payment?->id,
            'provider_reference' => $intent->provider_reference,
            'provider_payment_id' => $payment?->provider_payment_id,
            'booking_id' => $intent->booking_id,
            'package_product_id' => $intent->package_product_id,
            'client_id' => $intent->client_id,
            'professional_id' => $intent->professional_id,
            'amount' => $intent->amount,
            'currency' => $intent->currency,
            'raw_status' => $rawStatus,
        ];
    }
}
