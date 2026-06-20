<?php

namespace App\Actions\Payment;

use App\DTOs\Payment\ProviderPaymentStatus;
use App\Enums\Payment\PaymentIntentStatus;
use App\Enums\Payment\PaymentProvider;
use App\Enums\Payment\PaymentStatus;
use App\Exceptions\ApiException;
use App\Models\Payment\PaymentIntent;
use App\Models\User\User;
use App\Services\Payment\PaymentProviderManager;
use App\Services\Payment\Providers\MercadoPago\MercadoPagoPaymentProvider;
use App\Support\ActivityLog\ActivityLogActorMode;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

final readonly class SyncPaymentIntentProviderStatusAction
{
    public function __construct(
        private PaymentProviderManager $providers,
        private MarkPaymentSucceededAction $markPaymentSucceeded,
        private MarkPaymentFailedAction $markPaymentFailed,
    ) {}

    public function __invoke(PaymentIntent $paymentIntent, User $client): PaymentIntent
    {
        $intent = PaymentIntent::query()
            ->whereKey($paymentIntent->id)
            ->firstOrFail();

        if ($intent->client_id !== $client->id) {
            throw new ApiException(
                error: 'Forbidden',
                message: 'No puedes sincronizar este pago.',
                status: Response::HTTP_FORBIDDEN,
            );
        }

        if ($intent->isSucceeded()) {
            return $intent->load(['booking', 'packageProduct', 'payment']);
        }

        if (! in_array($intent->status, [
            PaymentIntentStatus::Pending,
            PaymentIntentStatus::CheckoutCreated,
            PaymentIntentStatus::Processing,
        ], true)) {
            throw new ApiException(
                error: 'PaymentIntentNotSyncable',
                message: 'Esta intención de pago ya no puede sincronizarse.',
                status: Response::HTTP_CONFLICT,
            );
        }

        $providerStatus = match ($intent->provider) {
            PaymentProvider::MercadoPago => $this->syncMercadoPago($intent),
            PaymentProvider::PayPal => $this->syncPayPal($intent),
            default => throw new ApiException(
                error: 'PaymentProviderSyncNotSupported',
                message: 'Este proveedor no soporta sincronización manual.',
                status: Response::HTTP_CONFLICT,
            ),
        };

        $this->validateProviderStatus($intent, $providerStatus);

        return DB::transaction(function () use ($intent, $providerStatus) {
            $lockedIntent = PaymentIntent::query()
                ->whereKey($intent->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedIntent->isSucceeded()) {
                return $lockedIntent->load(['booking', 'packageProduct', 'payment']);
            }

            match ($providerStatus->status) {
                PaymentStatus::Succeeded => ($this->markPaymentSucceeded)(
                    $lockedIntent,
                    $providerStatus,
                    ActivityLogActorMode::Client,
                ),

                PaymentStatus::Rejected,
                PaymentStatus::Failed,
                PaymentStatus::Cancelled => ($this->markPaymentFailed)(
                    $lockedIntent,
                    $providerStatus,
                    ActivityLogActorMode::Client,
                ),

                default => $lockedIntent->update([
                    'status' => PaymentIntentStatus::Processing,
                    'processing_at' => $lockedIntent->processing_at ?? now(),
                    'metadata' => [
                        ...(is_array($lockedIntent->metadata) ? $lockedIntent->metadata : []),
                        'provider_sync_status' => $providerStatus->rawStatus,
                        'provider_sync_at' => now()->toISOString(),
                    ],
                ]),
            };

            return $lockedIntent->refresh()->load(['booking', 'packageProduct', 'payment']);
        });
    }

    private function syncMercadoPago(PaymentIntent $intent): ProviderPaymentStatus
    {
        $gateway = $this->providers->driver(PaymentProvider::MercadoPago);

        if (! $gateway instanceof MercadoPagoPaymentProvider) {
            throw new ApiException(
                error: 'PaymentProviderMisconfigured',
                message: 'MercadoPago no está configurado correctamente.',
                status: Response::HTTP_SERVICE_UNAVAILABLE,
            );
        }

        return $gateway->searchPaymentByExternalReference((string) $intent->id)
            ?? throw new ApiException(
                error: 'ProviderPaymentNotFound',
                message: 'MercadoPago todavía no devolvió un pago asociado a esta intención.',
                status: Response::HTTP_CONFLICT,
            );
    }

    private function validateProviderStatus(
        PaymentIntent $intent,
        ProviderPaymentStatus $status
    ): void {
        if (
            $status->paymentIntentId !== null
            && ! hash_equals((string) $intent->id, (string) $status->paymentIntentId)
        ) {
            throw new ApiException(
                error: 'ProviderPaymentIntentMismatch',
                message: 'El pago del proveedor no corresponde a esta intención.',
                status: Response::HTTP_CONFLICT,
            );
        }

        if ($intent->provider === PaymentProvider::PayPal) {
            $expectedOrderId = (string) $intent->provider_reference;

            if ($expectedOrderId === '') {
                throw new ApiException(
                    error: 'PayPalOrderIdMissing',
                    message: 'La intención de pago no tiene una orden PayPal asociada.',
                    status: Response::HTTP_UNPROCESSABLE_ENTITY,
                );
            }

            if (
                $status->providerReference !== ''
                && ! hash_equals($expectedOrderId, (string) $status->providerReference)
            ) {
                throw new ApiException(
                    error: 'ProviderPaymentIdentityMismatch',
                    message: 'La orden devuelta por PayPal no corresponde al pago.',
                    status: Response::HTTP_CONFLICT,
                );
            }
        }

        if ($status->status !== PaymentStatus::Succeeded) {
            return;
        }

        [$expectedAmount, $expectedCurrency] = $this->expectedProviderMoney($intent);

        $providerAmount = $status->amount !== null
            ? number_format((float) $status->amount, 2, '.', '')
            : null;

        $providerCurrency = $status->currency !== null
            ? strtoupper((string) $status->currency)
            : null;

        if (
            $providerAmount === null
            || $providerCurrency === null
            || $providerAmount !== $expectedAmount
            || $providerCurrency !== $expectedCurrency
        ) {
            throw new ApiException(
                error: 'ProviderPaymentAmountMismatch',
                message: sprintf(
                    'El monto informado por el proveedor no coincide. Esperado: %s %s, recibido: %s %s.',
                    $expectedAmount,
                    $expectedCurrency,
                    $providerAmount ?? 'null',
                    $providerCurrency ?? 'null',
                ),
                status: Response::HTTP_CONFLICT,
            );
        }

        if ($status->providerPaymentId === null || $status->providerPaymentId === '') {
            throw new ApiException(
                error: 'ProviderPaymentIdMissing',
                message: 'El proveedor no devolvió el identificador final del pago.',
                status: Response::HTTP_BAD_GATEWAY,
            );
        }

        $alreadyUsed = Payment::query()
            ->where('provider', $intent->provider->value)
            ->where('provider_payment_id', $status->providerPaymentId)
            ->where('payment_intent_id', '!=', $intent->id)
            ->exists();

        if ($alreadyUsed) {
            throw new ApiException(
                error: 'ProviderPaymentIdentityMismatch',
                message: 'El pago del proveedor ya está asociado a otra intención.',
                status: Response::HTTP_CONFLICT,
            );
        }
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function expectedProviderMoney(PaymentIntent $intent): array
    {
        $metadata = is_array($intent->metadata) ? $intent->metadata : [];

        if ($intent->provider === PaymentProvider::PayPal) {
            if (
                isset($metadata['provider_amount'], $metadata['provider_currency'])
                && $metadata['provider_amount'] !== ''
                && $metadata['provider_currency'] !== ''
            ) {
                return [
                    number_format((float) $metadata['provider_amount'], 2, '.', ''),
                    strtoupper((string) $metadata['provider_currency']),
                ];
            }

            $exchangeRate = (float) ($metadata['exchange_rate'] ?? 0);

            if ($exchangeRate > 0) {
                return [
                    number_format((float) $intent->amount / $exchangeRate, 2, '.', ''),
                    strtoupper((string) config('services.paypal.currency', 'USD')),
                ];
            }
        }

        return [
            number_format((float) $intent->amount, 2, '.', ''),
            strtoupper((string) $intent->currency),
        ];
    }

    private function syncPayPal(PaymentIntent $intent): ProviderPaymentStatus
    {
        $gateway = $this->providers->driver(PaymentProvider::PayPal);

        if (! is_object($gateway) || ! method_exists($gateway, 'captureOrder')) {
            throw new ApiException(
                error: 'PaymentProviderMisconfigured',
                message: sprintf(
                    'PayPal no está configurado correctamente. Driver recibido: %s.',
                    get_debug_type($gateway),
                ),
                status: Response::HTTP_SERVICE_UNAVAILABLE,
            );
        }

        if (! is_string($intent->provider_reference) || $intent->provider_reference === '') {
            throw new ApiException(
                error: 'PayPalOrderIdMissing',
                message: 'La intención de pago no tiene una orden PayPal asociada.',
                status: Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        return $gateway->captureOrder((string) $intent->provider_reference);
    }
}
