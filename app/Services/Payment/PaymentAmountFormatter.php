<?php

namespace App\Services\Payment;

use App\Enums\Payment\PaymentProvider;
use App\Exceptions\ApiException;
use App\Models\Payment\PaymentIntent;
use Symfony\Component\HttpFoundation\Response;

final class PaymentAmountFormatter
{
    /**
     * @return array{amount: string, currency: string, exchange_rate: float|null}
     */
    public function forProvider(
        PaymentIntent $intent,
        PaymentProvider $provider
    ): array {
        if ($provider !== PaymentProvider::PayPal) {
            return [
                'amount' => $this->decimal($intent->amount),
                'currency' => $intent->currency,
                'exchange_rate' => null,
            ];
        }

        $targetCurrency = strtoupper(
            (string) config('services.paypal.currency', 'USD')
        );
        $sourceCurrency = strtoupper($intent->currency);

        if ($targetCurrency === $sourceCurrency) {
            return [
                'amount' => $this->decimal($intent->amount),
                'currency' => $targetCurrency,
                'exchange_rate' => null,
            ];
        }

        $rates = config('services.paypal.exchange_rates', []);
        $sourceUnitsPerTargetUnit = (float) ($rates[$sourceCurrency] ?? 0);

        if ($sourceUnitsPerTargetUnit <= 0) {
            throw new ApiException(
                error: 'PayPalExchangeRateNotConfigured',
                message: "No hay una tasa configurada para convertir {$sourceCurrency} a {$targetCurrency}.",
                status: Response::HTTP_SERVICE_UNAVAILABLE,
            );
        }

        return [
            'amount' => $this->decimal(
                (float) $intent->amount / $sourceUnitsPerTargetUnit
            ),
            'currency' => $targetCurrency,
            'exchange_rate' => $sourceUnitsPerTargetUnit,
        ];
    }

    private function decimal(int|float|string $amount): string
    {
        return number_format((float) $amount, 2, '.', '');
    }
}
