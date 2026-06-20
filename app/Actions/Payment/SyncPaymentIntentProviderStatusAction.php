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
            && $status->paymentIntentId !== (string) $intent->id
        ) {
            throw new ApiException(
                error: 'ProviderPaymentIntentMismatch',
                message: 'El pago del proveedor no corresponde a esta intención.',
                status: Response::HTTP_CONFLICT,
            );
        }

        if ((int) round((float) $status->amount) !== (int) $intent->amount) {
            throw new ApiException(
                error: 'ProviderAmountMismatch',
                message: 'El monto informado por el proveedor no coincide.',
                status: Response::HTTP_CONFLICT,
            );
        }

        if (
            $status->currency !== null
            && strtoupper((string) $status->currency) !== strtoupper((string) $intent->currency)
        ) {
            throw new ApiException(
                error: 'ProviderCurrencyMismatch',
                message: 'La moneda informada por el proveedor no coincide.',
                status: Response::HTTP_CONFLICT,
            );
        }
    }
}
