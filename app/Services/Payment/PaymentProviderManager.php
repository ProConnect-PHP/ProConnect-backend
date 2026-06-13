<?php

namespace App\Services\Payment;

use App\Contracts\Payment\IPaymentProviderGateway;
use App\Enums\Payment\PaymentProvider;
use InvalidArgumentException;

final class PaymentProviderManager
{
    /**
     * @var array<string, IPaymentProviderGateway>
     */
    private array $gateways = [];

    /**
     * @param  iterable<IPaymentProviderGateway>  $gateways
     */
    public function __construct(iterable $gateways)
    {
        foreach ($gateways as $gateway) {
            $this->gateways[$gateway->provider()->value] = $gateway;
        }
    }

    public function driver(PaymentProvider|string $provider): IPaymentProviderGateway
    {
        $providerValue = $provider instanceof PaymentProvider
            ? $provider->value
            : $provider;

        if (! isset($this->gateways[$providerValue])) {
            throw new InvalidArgumentException(
                "Unsupported payment provider [{$providerValue}]."
            );
        }

        return $this->gateways[$providerValue];
    }
}
