<?php

namespace App\Services\Payment;

use App\Http\Resources\Payment\PaymentIntentResource;
use App\Http\Resources\Payment\PaymentResource;
use App\Models\Payment\PaymentIntent;

final class PaymentIntentStatusPayload
{
    public function make(PaymentIntent $paymentIntent): array
    {
        $intent = $paymentIntent->load([
            'booking',
            'packageProduct',
            'payment.clientPackage',
        ]);
        $status = $intent->status?->value ?? (string) $intent->status;
        $isFinal = in_array($status, [
            'succeeded',
            'failed',
            'cancelled',
            'expired',
        ], true);

        return [
            'payment_intent' => new PaymentIntentResource($intent),
            'payment' => $intent->payment
                ? new PaymentResource($intent->payment)
                : null,
            'status' => $status,
            'provider' => $intent->provider?->value ?? $intent->provider,
            'provider_status' => $intent->payment?->raw_provider_status
                ?? data_get($intent->metadata, 'paypal_order_status')
                ?? data_get($intent->metadata, 'raw_provider_status')
                ?? data_get($intent->metadata, 'external_status'),
            'booking_status' => $intent->booking?->status?->value
                ?? $intent->booking?->status,
            'is_final' => $isFinal,
            'next_poll_after_seconds' => $isFinal
                ? null
                : ($status === 'processing' ? 5 : 3),
            'message' => $this->message($status, $isFinal),
        ];
    }

    private function message(string $status, bool $isFinal): string
    {
        if ($isFinal) {
            return match ($status) {
                'succeeded' => 'Tu pago fue confirmado.',
                'failed' => 'El proveedor rechazo el pago.',
                'cancelled' => 'El pago fue cancelado.',
                default => 'El intento de pago expiro.',
            };
        }

        return $status === 'checkout_created'
            ? 'Estamos esperando la confirmacion de PayPal.'
            : 'Tu pago esta siendo procesado. Te avisaremos cuando se confirme.';
    }
}
