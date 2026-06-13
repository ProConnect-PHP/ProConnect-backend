<?php

namespace App\Services\Payment\Providers\MercadoPago;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

final class MercadoPagoWebhookSignatureVerifier
{
    public function verify(Request $request): bool
    {
        $this->logDebugContext($request);

        $required = filter_var(
            config('services.mercadopago.webhook_signature_required', true),
            FILTER_VALIDATE_BOOLEAN
        );
        $secrets = $this->secrets();

        if ($secrets === []) {
            return ! $required && app()->environment(['local', 'testing']);
        }

        $signature = trim((string) $request->header('x-signature'));
        $requestId = trim((string) $request->header('x-request-id'));
        if ($signature === '' || $requestId === '') {
            return false;
        }

        $signatureParts = $this->parseSignature($signature);
        $timestamp = $signatureParts['ts'] ?? null;
        $receivedHash = $signatureParts['v1'] ?? null;

        if (
            ! is_string($timestamp)
            || $timestamp === ''
            || ! is_string($receivedHash)
            || $receivedHash === ''
            || ! $this->timestampIsWithinTolerance($timestamp)
        ) {
            return false;
        }

        foreach ($this->manifests($request, $requestId, $timestamp) as $manifestName => $manifest) {
            foreach ($secrets as $secretName => $secret) {
                $expectedHash = hash_hmac('sha256', $manifest, $secret);
                $matches = hash_equals($expectedHash, $receivedHash);

                $this->logSignatureComputation(
                    manifestName: $manifestName,
                    secretName: $secretName,
                    timestamp: $timestamp,
                    matches: $matches,
                );

                if ($matches) {
                    return true;
                }
            }
        }

        return false;
    }

    private function manifests(Request $request, string $requestId, string $timestamp): array
    {
        $queryDataId = $this->dataIdFromQuery($request);
        $bodyDataId = (string) data_get($request->all(), 'data.id');

        return array_filter([
            'query_data_id' => $queryDataId !== ''
                ? "id:{$queryDataId};request-id:{$requestId};ts:{$timestamp};"
                : null,

            'body_data_id' => $bodyDataId !== ''
                ? "id:{$bodyDataId};request-id:{$requestId};ts:{$timestamp};"
                : null,
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function secrets(): array
    {
        $secrets = [
            'default' => trim((string) config(
                'services.mercadopago.webhook_secret'
            )),
            'test' => trim((string) config(
                'services.mercadopago.webhook_secret_test'
            )),
            'production' => trim((string) config(
                'services.mercadopago.webhook_secret_production'
            )),
        ];

        return array_filter(
            $secrets,
            static fn (string $secret): bool => $secret !== ''
        );
    }

    /**
     * @return array<string, string>
     */
    private function parseSignature(string $signature): array
    {
        $result = [];

        foreach (explode(',', $signature) as $part) {
            [$key, $value] = array_pad(
                explode('=', trim($part), 2),
                2,
                null
            );

            if (
                is_string($key)
                && $key !== ''
                && is_string($value)
                && $value !== ''
            ) {
                $result[trim($key)] = trim($value);
            }
        }

        return $result;
    }

    private function dataIdFromQuery(Request $request): string
    {
        $query = $request->query->all();
        $dataId = $query['data.id']
            ?? data_get($query, 'data.id')
            ?? $query['data_id']
            ?? $query['id']
            ?? '';

        if (! is_scalar($dataId) || (string) $dataId === '') {
            $dataId = $this->dataIdFromRawQuery($request);
        }

        $dataId = is_scalar($dataId) ? (string) $dataId : '';

        return $dataId !== '' && ctype_alnum($dataId)
            ? mb_strtolower($dataId)
            : $dataId;
    }

    private function dataIdFromRawQuery(Request $request): string
    {
        foreach (explode('&', (string) $request->getQueryString()) as $part) {
            [$key, $value] = array_pad(explode('=', $part, 2), 2, '');

            if (rawurldecode($key) === 'data.id') {
                return rawurldecode($value);
            }
        }

        return '';
    }

    private function timestampIsWithinTolerance(string $timestamp): bool
    {
        if (! ctype_digit($timestamp)) {
            return false;
        }

        $seconds = (int) $timestamp;

        if ($seconds > 9_999_999_999) {
            $seconds = intdiv($seconds, 1000);
        }

        $tolerance = max(
            0,
            (int) config(
                'services.mercadopago.webhook_signature_tolerance_seconds',
                300
            )
        );

        return abs(time() - $seconds) <= $tolerance;
    }

    private function logDebugContext(Request $request): void
    {
        if (! config('app.debug')) {
            return;
        }

        Log::debug('MercadoPago webhook signature debug', [
            'x_signature_present' => $request->hasHeader('x-signature'),
            'x_request_id_present' => $request->hasHeader('x-request-id'),
            'query_data_id' => $this->dataIdFromQuery($request),
            'body_data_id' => data_get($request->all(), 'data.id'),
        ]);
    }

    private function logSignatureComputation(
        string $manifestName,
        string $secretName,
        string $timestamp,
        bool $matches,
    ): void {
        if (! config('app.debug')) {
            return;
        }

        $seconds = ctype_digit($timestamp) ? (int) $timestamp : 0;

        if ($seconds > 9_999_999_999) {
            $seconds = intdiv($seconds, 1000);
        }

        Log::debug('MercadoPago webhook signature computation', [
            'secret_name' => $secretName,
            'manifest_name' => $manifestName,
            'time_diff_seconds' => $seconds > 0 ? abs(time() - $seconds) : null,
            'hash_matches' => $matches,
        ]);
    }
}
