<?php

namespace Tests\Feature\Payment;

use App\Enums\Payment\PaymentProvider;
use App\Services\Payment\PaymentProviderManager;
use App\Services\Payment\Providers\MercadoPago\MercadoPagoPaymentProvider;
use App\Services\Payment\Providers\PayPal\PayPalPaymentProvider;
use App\Services\Payment\Providers\Simulator\SimulatorPaymentProvider;
use InvalidArgumentException;
use Tests\TestCase;

class PaymentProviderManagerTest extends TestCase
{
    public function test_it_resolves_all_supported_payment_providers(): void
    {
        $manager = app(PaymentProviderManager::class);

        $this->assertInstanceOf(
            SimulatorPaymentProvider::class,
            $manager->driver(PaymentProvider::Simulator)
        );
        $this->assertInstanceOf(
            MercadoPagoPaymentProvider::class,
            $manager->driver(PaymentProvider::MercadoPago)
        );
        $this->assertInstanceOf(
            PayPalPaymentProvider::class,
            $manager->driver(PaymentProvider::PayPal)
        );
    }

    public function test_it_rejects_an_unknown_payment_provider(): void
    {
        $this->expectException(InvalidArgumentException::class);

        app(PaymentProviderManager::class)->driver('unknown');
    }
}
