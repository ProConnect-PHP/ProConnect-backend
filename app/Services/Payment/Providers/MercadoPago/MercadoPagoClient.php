<?php

namespace App\Services\Payment\Providers\MercadoPago;

use App\Exceptions\ApiException;
use App\Models\Payment\PaymentIntent;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Uri;
use Symfony\Component\HttpFoundation\Response;

final class MercadoPagoClient
{
    private const BASE_URL = 'https://api.mercadopago.com';

    public function createPreference(PaymentIntent $intent): array
    {
        $payload = [
            'notification_url' => $this->notificationUrl(),
            'items' => [[
                'id' => (string) $intent->id,
                'title' => $this->titleFor($intent),
                'quantity' => 1,
                'currency_id' => (string) $intent->currency,
                'unit_price' => (float) $intent->amount,
            ]],
            'external_reference' => (string) $intent->id,
            'back_urls' => [
                'success' => $this->backUrl('success_url', $intent),
                'failure' => $this->backUrl('failure_url', $intent),
                'pending' => $this->backUrl('pending_url', $intent),
            ],
            'metadata' => [
                'payment_intent_id' => (string) $intent->id,
                'payable_type' => $intent->payable_type?->value
                    ?? $intent->payable_type,
                'payable_id' => (string) $intent->payable_id,
            ],

        ];

        if ($this->shouldUseAutoReturn()) {
            $payload['auto_return'] = 'approved';
        }

        $response = $this->request()
            ->withHeaders(['X-Idempotency-Key' => (string) $intent->id])
            ->post('/checkout/preferences', $payload);

        if ($response->failed()) {
            Log::error('MercadoPago create preference failed', [
                'payment_intent_id' => (string) $intent->id,
                'provider_status' => $response->status(),
                'provider_response' => $response->json(),
                'provider_raw_body' => $response->body(),
                'payload' => $payload,
            ]);

            throw $this->providerException(
                'MercadoPagoCheckoutFailed',
                'No se pudo crear el checkout de MercadoPago.',
                $response->status(),
                $response->json(),
                $response->body(),
            );
        }

        return $response->json();
    }

    public function findPaymentByExternalReference(string $externalReference): ?array
    {
        $response = $this->request()->get('/v1/payments/search', [
            'external_reference' => $externalReference,
            'sort' => 'date_created',
            'criteria' => 'desc',
        ]);

        if ($response->failed()) {
            Log::error('MercadoPago payment search by external_reference failed', [
                'external_reference' => $externalReference,
                'provider_status' => $response->status(),
                'provider_response' => $response->json(),
                'provider_raw_body' => $response->body(),
            ]);

            throw $this->providerException(
                'MercadoPagoPaymentSearchFailed',
                'No se pudo buscar el pago de MercadoPago por referencia externa.',
                $response->status(),
                $response->json(),
                $response->body(),
            );
        }

        $results = $response->json('results', []);

        if (! is_array($results) || $results === []) {
            return null;
        }

        /**
         * Priorizamos pagos aprobados.
         */
        foreach ($results as $payment) {
            if (
                is_array($payment)
                && ($payment['status'] ?? null) === 'approved'
            ) {
                return $payment;
            }
        }

        /**
         * Si todavía no hay aprobado, devolvemos el más reciente.
         */
        $first = $results[0] ?? null;

        return is_array($first) ? $first : null;
    }

    public function getPayment(string $paymentId): array
    {
        $response = $this->request()->get('/v1/payments/'.$paymentId);

        if ($response->failed()) {
            Log::error('MercadoPago payment lookup failed', [
                'payment_id' => $paymentId,
                'provider_status' => $response->status(),
                'provider_response' => $response->json(),
                'provider_raw_body' => $response->body(),
            ]);

            throw $this->providerException(
                'MercadoPagoPaymentLookupFailed',
                'No se pudo verificar el pago de MercadoPago.',
                $response->status(),
                $response->json(),
                $response->body(),
            );
        }

        return $response->json();
    }

    private function request(): PendingRequest
    {
        $accessToken = (string) config('services.mercadopago.access_token');

        if ($accessToken === '') {
            throw new ApiException(
                error: 'PaymentProviderNotConfigured',
                message: 'MercadoPago no esta configurado.',
                status: Response::HTTP_SERVICE_UNAVAILABLE,
            );
        }

        return Http::baseUrl(self::BASE_URL)
            ->withToken($accessToken)
            ->acceptJson()
            ->asJson()
            ->connectTimeout(5)
            ->timeout(10);
    }

    private function notificationUrl(): string
    {
        $url = (string) config('services.mercadopago.notification_url');

        if ($url === '') {
            throw new ApiException(
                error: 'MercadoPagoNotificationUrlNotConfigured',
                message: 'La URL de notificaciones de MercadoPago no esta configurada.',
                status: Response::HTTP_SERVICE_UNAVAILABLE,
            );
        }

        $parts = parse_url($url);
        $host = strtolower(trim((string) ($parts['host'] ?? ''), '[]'));
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $isIp = filter_var($host, FILTER_VALIDATE_IP) !== false;
        $isPublicIp = ! $isIp || filter_var(
            $host,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        ) !== false;

        if (
            ! in_array($scheme, ['http', 'https'], true)
            || $host === ''
            || $host === 'localhost'
            || str_ends_with($host, '.localhost')
            || ! $isPublicIp
        ) {
            throw new ApiException(
                error: 'MercadoPagoNotificationUrlInvalid',
                message: 'La URL de notificaciones de MercadoPago debe ser publica.',
                status: Response::HTTP_SERVICE_UNAVAILABLE,
            );
        }

        return (string) Uri::of($url)->withQuery(['source_news' => 'webhooks']);
    }

    private function backUrl(
        string $configKey,
        PaymentIntent $intent
    ): string {
        $url = (string) config('services.mercadopago.'.$configKey);

        if ($url === '' || filter_var($url, FILTER_VALIDATE_URL) === false) {
            throw new ApiException(
                error: 'MercadoPagoBackUrlNotConfigured',
                message: 'Las URLs de retorno de MercadoPago no estan configuradas.',
                status: Response::HTTP_SERVICE_UNAVAILABLE,
            );
        }

        return (string) Uri::of($url)->withQuery([
            'payment_intent_id' => (string) $intent->id,
        ]);
    }

    private function titleFor(PaymentIntent $intent): string
    {
        return match ($intent->payable_type?->value ?? $intent->payable_type) {
            'package' => 'Paquete ProConnect #'.$intent->id,
            default => 'Reserva ProConnect #'.$intent->id,
        };
    }

    private function shouldUseAutoReturn(): bool
    {
        $successUrl = (string) config('services.mercadopago.success_url');

        return str_starts_with(strtolower($successUrl), 'https://');
    }

    private function providerException(
        string $error,
        string $message,
        int $providerStatus,
        mixed $providerBody,
        string $providerRawBody,
    ): ApiException {
        $detailsBody = $providerBody ?? $providerRawBody;

        return new ApiException(
            error: $error,
            message: $message,
            status: Response::HTTP_BAD_GATEWAY,
            details: [
                'provider_status' => $providerStatus,
                'provider_error' => is_array($providerBody)
                    ? (
                        $providerBody['error']
                        ?? $providerBody['message']
                        ?? data_get($providerBody, 'cause.0.description')
                        ?? data_get($providerBody, 'cause.0.code')
                        ?? null
                    )
                    : null,
                'provider_body' => $detailsBody,
            ],
        );
    }

    public function searchPayments(array $filters): array
    {
        return $this->request('GET', '/v1/payments/search', [
            'query' => $filters,
        ]);
    }
}
