<?php

namespace App\Services\Payment\Providers\MercadoPago;

use App\Contracts\Payment\IPaymentProviderGateway;
use App\DTOs\Payment\PaymentCheckoutData;
use App\DTOs\Payment\ProviderPaymentStatus;
use App\DTOs\Payment\ProviderWebhookData;
use App\Enums\Payment\PaymentProvider;
use App\Exceptions\ApiException;
use App\Models\Payment\PaymentIntent;
use App\Services\Payment\PaymentPayloadSanitizer;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class MercadoPagoPaymentProvider implements IPaymentProviderGateway
{
    public function __construct(
        private MercadoPagoClient $client,
        private MercadoPagoEnvironmentResolver $environment,
        private MercadoPagoStatusMapper $statusMapper,
        private MercadoPagoWebhookSignatureVerifier $webhookVerifier,
        private PaymentPayloadSanitizer $sanitizer,
    ) {}

    public function provider(): PaymentProvider
    {
        return PaymentProvider::MercadoPago;
    }

    public function createCheckout(PaymentIntent $intent): PaymentCheckoutData
    {
        $preference = $this->client->createPreference($intent);
        $checkoutUrl = $this->checkoutUrl($preference);

        if ($checkoutUrl === '') {
            throw new ApiException(
                error: 'MercadoPagoCheckoutUrlMissing',
                message: 'Mercado Pago no devolvio una URL de checkout valida.',
                status: Response::HTTP_BAD_GATEWAY,
                details: [
                    'provider_environment' => $this->environment->mode(),
                    'preference_id' => $preference['id'] ?? null,
                    'has_init_point' => isset($preference['init_point']),
                    'has_sandbox_init_point' => isset(
                        $preference['sandbox_init_point']
                    ),
                ],
            );
        }

        return new PaymentCheckoutData(
            provider: PaymentProvider::MercadoPago,
            providerReference: (string) $preference['id'],
            checkoutUrl: $checkoutUrl,
            externalStatus: 'preference_created',
            metadata: [
                'provider_environment' => $this->environment->mode(),
                'preference_id' => isset($preference['id'])
                    ? (string) $preference['id']
                    : null,
                'init_point' => $preference['init_point'] ?? null,
                'sandbox_init_point' => $preference['sandbox_init_point'] ?? null,
            ],
        );
    }

    public function fetchPaymentStatus(string $providerReference): ProviderPaymentStatus
    {
        $payment = $this->client->getPayment($providerReference);
        $rawStatus = (string) ($payment['status'] ?? 'unknown');

        return new ProviderPaymentStatus(
            providerReference: (string) (
                $payment['order']['id']
                ?? $providerReference
            ),
            status: $this->statusMapper->map($rawStatus),
            rawStatus: $rawStatus,
            providerPaymentId: isset($payment['id']) ? (string) $payment['id'] : null,
            paymentIntentId: $this->paymentIntentId($payment),
            paidAt: $payment['date_approved'] ?? null,
            amount: isset($payment['transaction_amount'])
                ? (string) $payment['transaction_amount']
                : null,
            currency: isset($payment['currency_id'])
                ? (string) $payment['currency_id']
                : null,
            metadata: [
                'mercadopago_status' => $rawStatus,
                'status_detail' => $payment['status_detail'] ?? null,
                'external_reference' => $payment['external_reference'] ?? null,
                'payment_type_id' => $payment['payment_type_id'] ?? null,
                'payment_method_id' => $payment['payment_method_id'] ?? null,
                'verification_mode' => 'provider_api_lookup',
            ],
        );
    }

    public function parseWebhook(Request $request): ProviderWebhookData
    {
        $resourceType = $this->webhookType($request);
        $resourceId = $this->resourceIdFromRequest($request);

        $eventType = $request->input('action')
            ?? $request->query('type')
            ?? $request->input('type')
            ?? 'payment.updated';

        $providerEventId = $request->input('id');

        if (! is_scalar($providerEventId) || (string) $providerEventId === '') {
            $providerEventId = hash('sha256', implode('|', [
                'mercadopago',
                (string) $eventType,
                (string) $resourceId,
            ]));
        }

        $signatureValid = $this->isProcessablePaymentWebhook(
            $resourceType,
            $resourceId
        )
            ? $this->webhookVerifier->verify($request)
            : true;

        return new ProviderWebhookData(
            provider: PaymentProvider::MercadoPago,
            providerEventId: (string) $providerEventId,
            eventType: (string) $eventType,
            resourceType: $resourceType !== '' ? $resourceType : 'unknown',
            resourceId: $resourceId,
            signatureValid: $signatureValid,
            payload: $this->sanitizer->sanitize([
                'id' => $request->input('id'),
                'live_mode' => $request->input('live_mode'),
                'type' => $request->input('type') ?? $request->query('type'),
                'topic' => $request->input('topic') ?? $request->query('topic'),
                'action' => $request->input('action'),
                'api_version' => $request->input('api_version'),
                'data' => ['id' => $resourceId],
            ]),
        );
    }

    private function webhookType(Request $request): string
    {
        return strtolower(trim((string) (
            $request->input('type')
            ?? $request->query('type')
            ?? $request->input('topic')
            ?? $request->query('topic')
            ?? ''
        )));
    }

    private function resourceIdFromRequest(Request $request): ?string
    {
        $query = $request->query->all();

        $resourceId = $request->input('data.id')
            ?? $query['data.id']
            ?? data_get($query, 'data.id')
            ?? $query['data_id']
            ?? $request->input('id')
            ?? $query['id']
            ?? null;

        return is_scalar($resourceId) && (string) $resourceId !== ''
            ? (string) $resourceId
            : null;
    }

    private function isProcessablePaymentWebhook(
        string $resourceType,
        ?string $resourceId
    ): bool {
        return $resourceType === 'payment'
            && $resourceId !== null
            && ctype_digit($resourceId);
    }

    private function paymentIntentId(array $payment): ?string
    {
        $intentId = $payment['external_reference']
            ?? data_get($payment, 'metadata.payment_intent_id');

        return is_scalar($intentId) && (string) $intentId !== ''
            ? (string) $intentId
            : null;
    }

    private function checkoutUrl(array $preference): string
    {
        if ($this->environment->isSandbox()) {
            return (string) (
                $preference['sandbox_init_point']
                ?? $preference['init_point']
                ?? ''
            );
        }

        return (string) (
            $preference['init_point']
            ?? $preference['sandbox_init_point']
            ?? ''
        );
    }
}
