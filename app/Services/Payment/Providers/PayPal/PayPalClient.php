<?php

namespace App\Services\Payment\Providers\PayPal;

use App\Exceptions\ApiException;
use App\Models\Payment\PaymentIntent;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\Response;

final class PayPalClient
{
    public function createOrder(
        PaymentIntent $intent,
        string $amount,
        string $currency
    ): array {
        $response = $this->authenticatedRequest()
            ->withHeaders(['PayPal-Request-Id' => (string) $intent->id])
            ->post('/v2/checkout/orders', [
                'intent' => 'CAPTURE',
                'purchase_units' => [[
                    'reference_id' => (string) $intent->id,
                    'custom_id' => (string) $intent->id,
                    'description' => $this->descriptionFor($intent),
                    'amount' => [
                        'currency_code' => $currency,
                        'value' => $amount,
                    ],
                ]],
                'payment_source' => [
                    'paypal' => [
                        'experience_context' => [
                            'return_url' => $this->returnUrl($intent),
                            'cancel_url' => $this->cancelUrl($intent),
                            'user_action' => 'PAY_NOW',
                        ],
                    ],
                ],
            ]);

        if ($response->failed()) {
            throw $this->providerException(
                'PayPalOrderCreationFailed',
                'No se pudo crear la orden de PayPal.',
                $response->status(),
                $response->json()
            );
        }

        return $response->json();
    }

    public function getOrder(string $orderId): array
    {
        $response = $this->authenticatedRequest()
            ->get('/v2/checkout/orders/'.$orderId);

        if ($response->failed()) {
            throw $this->providerException(
                'PayPalOrderLookupFailed',
                'No se pudo verificar la orden de PayPal.',
                $response->status(),
                $response->json()
            );
        }

        return $response->json();
    }

    public function captureOrder(string $orderId): array
    {
        $response = $this->authenticatedRequest()
            ->withHeaders([
                'PayPal-Request-Id' => 'capture-'.$orderId,
            ])
            ->post('/v2/checkout/orders/'.$orderId.'/capture', (object) []);

        if ($response->failed()) {
            report(new ApiException(
                error: 'PayPalCaptureFailedDebug',
                message: 'PayPal capture failed with provider response.',
                status: Response::HTTP_BAD_GATEWAY,
                details: [
                    'order_id' => $orderId,
                    'paypal_http_status' => $response->status(),
                    'paypal_response' => $response->json(),
                    'paypal_raw_body' => $response->body(),
                ],
            ));

            throw $this->providerException(
                'PayPalCaptureFailed',
                'No se pudo capturar el pago de PayPal.',
                $response->status(),
                $response->json()
            );
        }

        return $response->json();
    }

    public function verifyWebhookSignature(Request $request): bool
    {
        $webhookId = (string) config('services.paypal.webhook_id');

        if ($webhookId === '') {
            return app()->environment(['local', 'testing']);
        }

        $headers = [
            'auth_algo' => $request->header('PAYPAL-AUTH-ALGO'),
            'cert_url' => $request->header('PAYPAL-CERT-URL'),
            'transmission_id' => $request->header('PAYPAL-TRANSMISSION-ID'),
            'transmission_sig' => $request->header('PAYPAL-TRANSMISSION-SIG'),
            'transmission_time' => $request->header('PAYPAL-TRANSMISSION-TIME'),
        ];

        if (in_array(null, $headers, true) || in_array('', $headers, true)) {
            return false;
        }

        $response = $this->authenticatedRequest()
            ->post('/v1/notifications/verify-webhook-signature', [
                ...$headers,
                'webhook_id' => $webhookId,
                'webhook_event' => $request->all(),
            ]);

        return $response->successful()
            && $response->json('verification_status') === 'SUCCESS';
    }

    private function authenticatedRequest(): PendingRequest
    {
        return Http::baseUrl($this->baseUrl())
            ->withToken($this->accessToken())
            ->acceptJson()
            ->asJson()
            ->connectTimeout(5)
            ->timeout(10);
    }

    private function accessToken(): string
    {
        $clientId = (string) config('services.paypal.client_id');
        $clientSecret = (string) config('services.paypal.client_secret');

        if ($clientId === '' || $clientSecret === '') {
            throw new ApiException(
                error: 'PaymentProviderNotConfigured',
                message: 'PayPal no esta configurado.',
                status: Response::HTTP_SERVICE_UNAVAILABLE,
            );
        }

        $response = Http::baseUrl($this->baseUrl())
            ->asForm()
            ->withBasicAuth($clientId, $clientSecret)
            ->connectTimeout(5)
            ->timeout(10)
            ->post('/v1/oauth2/token', [
                'grant_type' => 'client_credentials',
            ]);

        if ($response->failed() || ! is_string($response->json('access_token'))) {
            throw $this->providerException(
                'PayPalAuthFailed',
                'No se pudo autenticar contra PayPal.',
                $response->status(),
                $response->json()
            );
        }

        return $response->json('access_token');
    }

    private function baseUrl(): string
    {
        return config('services.paypal.mode') === 'live'
            ? 'https://api-m.paypal.com'
            : 'https://api-m.sandbox.paypal.com';
    }

    private function returnUrl(PaymentIntent $intent): string
    {
        return $this->appendIntentId(
            (string) config('services.paypal.success_url'),
            $intent
        );
    }

    private function cancelUrl(PaymentIntent $intent): string
    {
        return $this->appendIntentId(
            (string) config('services.paypal.cancel_url'),
            $intent
        );
    }

    private function appendIntentId(string $url, PaymentIntent $intent): string
    {
        return $url.(str_contains($url, '?') ? '&' : '?')
            .'payment_intent_id='.$intent->id;
    }

    private function descriptionFor(PaymentIntent $intent): string
    {
        return match ($intent->payable_type?->value ?? $intent->payable_type) {
            'package' => 'Paquete ProConnect',
            default => 'Reserva ProConnect',
        };
    }

    private function providerException(
        string $error,
        string $message,
        int $providerStatus,
        mixed $providerBody
    ): ApiException {
        $providerError = null;

        if (is_array($providerBody)) {
            $providerError = $providerBody['name']
                ?? $providerBody['message']
                ?? data_get($providerBody, 'details.0.issue')
                ?? null;
        }

        return new ApiException(
            error: $error,
            message: $message,
            status: Response::HTTP_BAD_GATEWAY,
            details: [
                'provider_status' => $providerStatus,
                'provider_error' => $providerError,
                'provider_body' => $providerBody,
            ],
        );
    }
}
