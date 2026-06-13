<?php

namespace App\Services\Payment\Providers\PayPal;

use App\Contracts\Payment\IPaymentProviderGateway;
use App\DTOs\Payment\PaymentCheckoutData;
use App\DTOs\Payment\ProviderPaymentStatus;
use App\DTOs\Payment\ProviderWebhookData;
use App\Enums\Payment\PaymentProvider;
use App\Exceptions\ApiException;
use App\Models\Payment\PaymentIntent;
use App\Services\Payment\PaymentAmountFormatter;
use App\Services\Payment\PaymentPayloadSanitizer;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class PayPalPaymentProvider implements IPaymentProviderGateway
{
    public function __construct(
        private PayPalClient $client,
        private PayPalStatusMapper $statusMapper,
        private PayPalWebhookVerifier $webhookVerifier,
        private PaymentAmountFormatter $amountFormatter,
        private PaymentPayloadSanitizer $sanitizer,
    ) {}

    public function provider(): PaymentProvider
    {
        return PaymentProvider::PayPal;
    }

    public function createCheckout(PaymentIntent $intent): PaymentCheckoutData
    {
        $providerAmount = $this->amountFormatter->forProvider(
            $intent,
            PaymentProvider::PayPal
        );
        $order = $this->client->createOrder(
            $intent,
            $providerAmount['amount'],
            $providerAmount['currency']
        );
        $approvalUrl = collect($order['links'] ?? [])
            ->firstWhere('rel', 'payer-action')['href']
            ?? collect($order['links'] ?? [])->firstWhere('rel', 'approve')['href']
            ?? null;

        if (! is_string($approvalUrl) || $approvalUrl === '') {
            throw new ApiException(
                error: 'PayPalApprovalUrlMissing',
                message: 'PayPal no devolvio una URL de aprobacion.',
                status: Response::HTTP_BAD_GATEWAY,
            );
        }

        return new PaymentCheckoutData(
            provider: PaymentProvider::PayPal,
            providerReference: (string) $order['id'],
            checkoutUrl: $approvalUrl,
            externalStatus: $order['status'] ?? null,
            metadata: [
                'order_id' => (string) $order['id'],
                'provider_amount' => $providerAmount['amount'],
                'provider_currency' => $providerAmount['currency'],
                'exchange_rate' => $providerAmount['exchange_rate'],
            ],
        );
    }

    public function fetchPaymentStatus(string $providerReference): ProviderPaymentStatus
    {
        $order = $this->client->getOrder($providerReference);

        if (strtoupper((string) ($order['status'] ?? '')) === 'APPROVED') {
            $order = $this->client->captureOrder($providerReference);
        }

        $rawStatus = (string) ($order['status'] ?? 'unknown');
        $capture = $order['purchase_units'][0]['payments']['captures'][0] ?? [];
        $intentId = $order['purchase_units'][0]['reference_id']
            ?? $order['purchase_units'][0]['custom_id']
            ?? null;

        return new ProviderPaymentStatus(
            providerReference: (string) ($order['id'] ?? $providerReference),
            status: $this->statusMapper->map($rawStatus),
            rawStatus: $rawStatus,
            providerPaymentId: isset($capture['id']) ? (string) $capture['id'] : null,
            paymentIntentId: $intentId !== null ? (string) $intentId : null,
            paidAt: $capture['update_time'] ?? $order['update_time'] ?? null,
            amount: data_get($capture, 'amount.value')
                ?? data_get($order, 'purchase_units.0.amount.value'),
            currency: data_get($capture, 'amount.currency_code')
                ?? data_get($order, 'purchase_units.0.amount.currency_code'),
            metadata: [
                'capture_status' => $capture['status'] ?? null,
            ],
        );
    }

    public function parseWebhook(Request $request): ProviderWebhookData
    {
        $eventType = $request->input('event_type');
        $resource = $request->input('resource', []);
        $resourceId = data_get($resource, 'supplementary_data.related_ids.order_id')
            ?? data_get($resource, 'id');

        return new ProviderWebhookData(
            provider: PaymentProvider::PayPal,
            providerEventId: $request->input('id') !== null
                ? (string) $request->input('id')
                : null,
            eventType: $eventType !== null ? (string) $eventType : null,
            resourceType: $request->input('resource_type') !== null
                ? (string) $request->input('resource_type')
                : null,
            resourceId: $resourceId !== null ? (string) $resourceId : null,
            signatureValid: $this->webhookVerifier->verify($request),
            payload: $this->sanitizer->sanitize([
                'id' => $request->input('id'),
                'event_type' => $eventType,
                'resource_type' => $request->input('resource_type'),
                'create_time' => $request->input('create_time'),
                'resource' => [
                    'id' => data_get($resource, 'id'),
                    'status' => data_get($resource, 'status'),
                    'custom_id' => data_get($resource, 'custom_id'),
                    'supplementary_data' => data_get($resource, 'supplementary_data'),
                ],
            ]),
        );
    }
}
