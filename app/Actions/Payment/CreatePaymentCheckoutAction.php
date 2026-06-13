<?php

namespace App\Actions\Payment;

use App\Enums\Payment\PaymentIntentStatus;
use App\Enums\Payment\PaymentProvider;
use App\Exceptions\ApiException;
use App\Models\Payment\PaymentIntent;
use App\Models\User\User;
use App\Services\Payment\PaymentPayloadSanitizer;
use App\Services\Payment\PaymentProviderManager;
use App\Support\ActivityLog\ActivityLogActorMode;
use App\Support\ActivityLog\ActivityLogEvent;
use App\Support\ActivityLog\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

final readonly class CreatePaymentCheckoutAction
{
    public function __construct(
        private PaymentProviderManager $providers,
        private PaymentPayloadSanitizer $sanitizer,
        private ActivityLogger $activityLogger,
    ) {}

    public function __invoke(
        PaymentIntent $paymentIntent,
        PaymentProvider $provider,
        User $client
    ): PaymentIntent {
        $intent = DB::transaction(function () use (
            $paymentIntent,
            $provider,
            $client
        ): PaymentIntent {
            $intent = PaymentIntent::query()
                ->whereKey($paymentIntent->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($intent->client_id !== $client->id) {
                throw new ApiException(
                    error: 'Forbidden',
                    message: 'No puedes crear el checkout de este pago.',
                    status: Response::HTTP_FORBIDDEN,
                );
            }

            if ($intent->isExpired() || $intent->status === PaymentIntentStatus::Expired) {
                $intent->update(['status' => PaymentIntentStatus::Expired]);

                throw new ApiException(
                    error: 'PaymentIntentExpired',
                    message: 'El intento de pago expiro.',
                    status: Response::HTTP_CONFLICT,
                );
            }

            if ($intent->hasCheckout() && $intent->provider === $provider) {
                return $intent->load(['booking', 'packageProduct', 'payment']);
            }

            if (! in_array($intent->status, [
                PaymentIntentStatus::Pending,
                PaymentIntentStatus::CheckoutCreated,
            ], true)) {
                throw new ApiException(
                    error: 'PaymentIntentNotProcessable',
                    message: 'La intencion de pago no puede crear un checkout.',
                    status: Response::HTTP_CONFLICT,
                );
            }

            $enabledProviders = config('proconnect.payments.enabled_providers', []);

            if (! in_array($provider->value, $enabledProviders, true)) {
                throw new ApiException(
                    error: 'UnsupportedPaymentProvider',
                    message: 'El proveedor de pago no esta habilitado.',
                    status: Response::HTTP_UNPROCESSABLE_ENTITY,
                );
            }

            if (
                $provider === PaymentProvider::Simulator
                && ! config('proconnect.payments.simulator.enabled')
            ) {
                throw new ApiException(
                    error: 'PaymentSimulatorDisabled',
                    message: 'El simulador de pagos no esta habilitado.',
                    status: Response::HTTP_FORBIDDEN,
                );
            }

            $checkout = $this->providers->driver($provider)->createCheckout($intent);

            $intent->update([
                'provider' => $provider,
                'provider_reference' => $checkout->providerReference,
                'checkout_url' => $checkout->checkoutUrl,
                'status' => PaymentIntentStatus::CheckoutCreated,
                'metadata' => $this->sanitizer->sanitize([
                    ...($intent->metadata ?? []),
                    ...$checkout->metadata,
                    'external_status' => $checkout->externalStatus,
                ]),
            ]);

            return $intent->refresh()->load([
                'booking',
                'packageProduct',
                'payment',
            ]);
        });

        $this->activityLogger->record(
            event: ActivityLogEvent::PaymentCheckoutCreated,
            entityType: 'payment_intent',
            entityId: $intent->id,
            entityOwnerId: $intent->professional_id,
            metadata: [
                'payment_intent_id' => $intent->id,
                'provider' => $intent->provider,
                'provider_reference' => $intent->provider_reference,
                'booking_id' => $intent->booking_id,
                'package_product_id' => $intent->package_product_id,
                'client_id' => $intent->client_id,
                'professional_id' => $intent->professional_id,
                'amount' => $intent->amount,
                'currency' => $intent->currency,
            ],
            actor: $client,
            actingAs: ActivityLogActorMode::Client,
        );

        return $intent;
    }
}
