<?php

namespace App\Services\Payment\Providers\Simulator;

use App\Contracts\Payment\IPaymentProviderGateway;
use App\DTOs\Payment\PaymentCheckoutData;
use App\DTOs\Payment\ProviderPaymentStatus;
use App\DTOs\Payment\ProviderWebhookData;
use App\Enums\Payment\PaymentProvider;
use App\Enums\Payment\PaymentStatus;
use App\Models\Payment\PaymentIntent;
use App\Services\Payment\PaymentPayloadSanitizer;
use Illuminate\Http\Request;

final readonly class SimulatorPaymentProvider implements IPaymentProviderGateway
{
    public function __construct(
        private PaymentPayloadSanitizer $sanitizer,
    ) {}

    public function provider(): PaymentProvider
    {
        return PaymentProvider::Simulator;
    }

    public function createCheckout(PaymentIntent $intent): PaymentCheckoutData
    {
        return new PaymentCheckoutData(
            provider: PaymentProvider::Simulator,
            providerReference: $intent->provider_reference ?: 'sim_'.$intent->id,
            checkoutUrl: rtrim(
                (string) config('proconnect.frontend_url', config('app.url')),
                '/'
            ).'/payments/simulator/'.$intent->id,
            externalStatus: 'created',
            metadata: ['mode' => 'simulator'],
        );
    }

    public function fetchPaymentStatus(string $providerReference): ProviderPaymentStatus
    {
        return new ProviderPaymentStatus(
            providerReference: $providerReference,
            status: PaymentStatus::Succeeded,
            rawStatus: 'approved',
            providerPaymentId: $providerReference,
        );
    }

    public function parseWebhook(Request $request): ProviderWebhookData
    {
        return new ProviderWebhookData(
            provider: PaymentProvider::Simulator,
            providerEventId: $request->string('event_id')->toString() ?: null,
            eventType: $request->string('type', 'payment.approved')->toString(),
            resourceType: 'payment',
            resourceId: $request->string('payment_intent_id')->toString() ?: null,
            signatureValid: true,
            payload: $this->sanitizer->sanitize($request->all()),
        );
    }
}
