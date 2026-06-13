<?php

namespace App\Services\Payment\Providers\MercadoPago;

final class MercadoPagoEnvironmentResolver
{
    public function mode(): string
    {
        $mode = strtolower(trim(
            (string) config('services.mercadopago.mode', 'sandbox')
        ));

        return in_array($mode, ['sandbox', 'production'], true)
            ? $mode
            : 'sandbox';
    }

    public function isSandbox(): bool
    {
        return $this->mode() === 'sandbox';
    }

    public function isProduction(): bool
    {
        return $this->mode() === 'production';
    }
}
