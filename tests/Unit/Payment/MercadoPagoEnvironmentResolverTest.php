<?php

namespace Tests\Unit\Payment;

use App\Services\Payment\Providers\MercadoPago\MercadoPagoEnvironmentResolver;
use Tests\TestCase;

class MercadoPagoEnvironmentResolverTest extends TestCase
{
    public function test_it_resolves_sandbox_mode(): void
    {
        config()->set('services.mercadopago.mode', 'sandbox');

        $environment = app(MercadoPagoEnvironmentResolver::class);

        $this->assertSame('sandbox', $environment->mode());
        $this->assertTrue($environment->isSandbox());
        $this->assertFalse($environment->isProduction());
    }

    public function test_it_resolves_production_mode(): void
    {
        config()->set('services.mercadopago.mode', 'production');

        $environment = app(MercadoPagoEnvironmentResolver::class);

        $this->assertSame('production', $environment->mode());
        $this->assertFalse($environment->isSandbox());
        $this->assertTrue($environment->isProduction());
    }

    public function test_invalid_mode_falls_back_to_sandbox(): void
    {
        config()->set('services.mercadopago.mode', 'invalid');

        $this->assertSame(
            'sandbox',
            app(MercadoPagoEnvironmentResolver::class)->mode()
        );
    }

    public function test_test_token_prefix_does_not_define_mode(): void
    {
        config()->set('services.mercadopago.mode', 'production');
        config()->set('services.mercadopago.access_token', 'TEST-token');

        $this->assertSame(
            'production',
            app(MercadoPagoEnvironmentResolver::class)->mode()
        );
    }

    public function test_app_usr_token_prefix_does_not_define_mode(): void
    {
        config()->set('services.mercadopago.mode', 'sandbox');
        config()->set('services.mercadopago.access_token', 'APP_USR-token');

        $this->assertSame(
            'sandbox',
            app(MercadoPagoEnvironmentResolver::class)->mode()
        );
    }
}
