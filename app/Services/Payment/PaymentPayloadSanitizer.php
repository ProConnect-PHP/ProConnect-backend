<?php

namespace App\Services\Payment;

use BackedEnum;
use DateTimeInterface;

final class PaymentPayloadSanitizer
{
    private const SENSITIVE_KEYS = [
        'access_token',
        'authorization',
        'card',
        'card_number',
        'client_secret',
        'cvv',
        'payer',
        'password',
        'refresh_token',
        'secret',
        'token',
    ];

    public function sanitize(array $payload): array
    {
        return $this->sanitizeArray($payload, 0);
    }

    private function sanitizeArray(array $payload, int $depth): array
    {
        if ($depth >= 8) {
            return ['truncated' => true];
        }

        foreach ($payload as $key => $value) {
            if (in_array(strtolower((string) $key), self::SENSITIVE_KEYS, true)) {
                $payload[$key] = '[redacted]';

                continue;
            }

            $payload[$key] = match (true) {
                is_array($value) => $this->sanitizeArray($value, $depth + 1),
                $value instanceof BackedEnum => $value->value,
                $value instanceof DateTimeInterface => $value->format(DATE_ATOM),
                is_object($value) => $value::class,
                is_string($value) => mb_substr($value, 0, 2000),
                default => $value,
            };
        }

        return $payload;
    }
}
