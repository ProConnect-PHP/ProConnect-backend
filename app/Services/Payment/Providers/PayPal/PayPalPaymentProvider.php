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
                'paypal_order_id' => (string) $order['id'],
                'paypal_order_status' => $order['status'] ?? null,
                'approval_url' => $approvalUrl,
                'paypal_custom_id' => (string) $intent->id,
                'paypal_reference_id' => (string) $intent->id,
                'provider_amount' => $providerAmount['amount'],
                'provider_currency' => $providerAmount['currency'],
                'exchange_rate' => $providerAmount['exchange_rate'],
            ],
        );
    }

    public function fetchPaymentStatus(string $providerReference): ProviderPaymentStatus
    {
        return $this->captureOrder($providerReference);
    }

    /**
     * Captures an approved PayPal order, or returns its final state when the
     * provider has already completed the capture. This is safe to retry:
     * PayPal receives a stable PayPal-Request-Id from the client adapter.
     */
    public function captureOrder(string $paypalOrderId): ProviderPaymentStatus
    {
        $order = $this->client->getOrder($paypalOrderId);

        if (strtoupper((string) ($order['status'] ?? '')) === 'APPROVED') {
            $order = $this->client->captureOrder($paypalOrderId);
        }

        return $this->providerStatusFromOrder($order, $paypalOrderId);
    }

    /**
     * Looks up an order without attempting a capture. This is used only to
     * complete signed capture events that omit amount or currency fields.
     */
    public function fetchOrderStatus(string $paypalOrderId): ProviderPaymentStatus
    {
        return $this->providerStatusFromOrder(
            $this->client->getOrder($paypalOrderId),
            $paypalOrderId,
        );
    }

    /**
     * Builds a status from a signed PayPal event. Capture events are already
     * definitive provider statements, so they must not trigger another
     * capture request.
     */
    public function statusFromWebhook(
        ProviderWebhookData $webhook
    ): ProviderPaymentStatus {
        $resource = $webhook->payload['resource'] ?? [];
        $purchaseUnit = data_get($resource, 'purchase_units.0', []);
        $paypalOrderId = data_get(
            $resource,
            'supplementary_data.related_ids.order_id'
        )
            ?? data_get($resource, 'id');
        $isCaptureEvent = str_starts_with(
            strtoupper((string) $webhook->eventType),
            'PAYMENT.CAPTURE.'
        );
        $paymentIntentId = data_get($resource, 'custom_id')
            ?? data_get($purchaseUnit, 'custom_id')
            ?? data_get($purchaseUnit, 'reference_id');
        $captureId = $isCaptureEvent ? data_get($resource, 'id') : null;
        $rawStatus = (string) (data_get($resource, 'status') ?? 'unknown');
        $eventType = strtoupper((string) $webhook->eventType);

        if ($eventType === 'CHECKOUT.ORDER.APPROVED') {
            if (is_scalar($paypalOrderId) && (string) $paypalOrderId !== '') {
                return $this->captureOrder((string) $paypalOrderId);
            }
        }

        return new ProviderPaymentStatus(
            providerReference: (string) ($paypalOrderId ?? $webhook->resourceId),
            status: $this->statusMapper->map($rawStatus),
            rawStatus: $rawStatus,
            providerPaymentId: is_scalar($captureId) && (string) $captureId !== ''
                ? (string) $captureId
                : null,
            paymentIntentId: is_scalar($paymentIntentId)
                && (string) $paymentIntentId !== ''
                ? (string) $paymentIntentId
                : null,
            paidAt: data_get($resource, 'update_time')
                ?? data_get($resource, 'create_time'),
            amount: data_get($resource, 'amount.value')
                ?? data_get($purchaseUnit, 'amount.value'),
            currency: data_get($resource, 'amount.currency_code')
                ?? data_get($purchaseUnit, 'amount.currency_code'),
            metadata: [
                'paypal_order_id' => $paypalOrderId,
                'paypal_order_status' => $rawStatus,
                'paypal_capture_id' => $captureId,
                'paypal_event_type' => $webhook->eventType,
            ],
        );
    }

    private function providerStatusFromOrder(
        array $order,
        string $fallbackOrderId
    ): ProviderPaymentStatus {
        $capture = $order['purchase_units'][0]['payments']['captures'][0] ?? [];
        $orderStatus = (string) ($order['status'] ?? 'unknown');
        $rawStatus = (string) ($capture['status'] ?? $orderStatus);
        $intentId = $order['purchase_units'][0]['reference_id']
            ?? $order['purchase_units'][0]['custom_id']
            ?? null;

        return new ProviderPaymentStatus(
            providerReference: (string) ($order['id'] ?? $fallbackOrderId),
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
                'paypal_order_id' => $order['id'] ?? $fallbackOrderId,
                'paypal_order_status' => $orderStatus,
                'paypal_capture_id' => $capture['id'] ?? null,
                'capture_status' => $capture['status'] ?? null,
                'debug_id' => $order['debug_id'] ?? null,
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
                    'invoice_id' => data_get($resource, 'invoice_id'),
                    'amount' => data_get($resource, 'amount'),
                    'purchase_units' => data_get($resource, 'purchase_units'),
                    'supplementary_data' => data_get($resource, 'supplementary_data'),
                ],
            ]),
        );
    }
}
