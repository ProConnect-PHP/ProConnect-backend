<?php

namespace Tests\Unit\Payment;

use App\Services\Payment\Providers\MercadoPago\MercadoPagoPaymentProvider;
use Illuminate\Http\Request;
use Tests\TestCase;

class MercadoPagoPaymentProviderWebhookTest extends TestCase
{
    private MercadoPagoPaymentProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set(
            'services.mercadopago.webhook_secret',
            'test-webhook-secret'
        );
        config()->set('services.mercadopago.webhook_secret_test', null);
        config()->set(
            'services.mercadopago.webhook_secret_production',
            null
        );
        config()->set(
            'services.mercadopago.webhook_signature_required',
            true
        );
        config()->set(
            'services.mercadopago.webhook_signature_tolerance_seconds',
            300
        );

        $this->provider = app(MercadoPagoPaymentProvider::class);
    }

    public function test_payment_with_numeric_id_is_signature_verified(): void
    {
        $webhook = $this->provider->parseWebhook(
            $this->signedRequest('123456')
        );

        $this->assertSame('payment', $webhook->resourceType);
        $this->assertSame('123456', $webhook->resourceId);
        $this->assertTrue($webhook->signatureValid);
    }

    public function test_merchant_order_skips_signature_verification(): void
    {
        $request = Request::create(
            '/webhook?data.id=41788606511&type=topic_merchant_order_wh',
            'POST'
        );

        $webhook = $this->provider->parseWebhook($request);

        $this->assertSame(
            'topic_merchant_order_wh',
            $webhook->resourceType
        );
        $this->assertSame('41788606511', $webhook->resourceId);
        $this->assertTrue($webhook->signatureValid);
    }

    public function test_payment_with_non_numeric_id_skips_signature_verification(): void
    {
        $request = Request::create(
            '/webhook?data.id=abc&type=payment',
            'POST'
        );

        $webhook = $this->provider->parseWebhook($request);

        $this->assertSame('payment', $webhook->resourceType);
        $this->assertSame('abc', $webhook->resourceId);
        $this->assertTrue($webhook->signatureValid);
    }

    public function test_missing_type_is_classified_as_unknown(): void
    {
        $request = Request::create(
            '/webhook?data.id=41788606511',
            'POST'
        );

        $webhook = $this->provider->parseWebhook($request);

        $this->assertSame('unknown', $webhook->resourceType);
        $this->assertSame('41788606511', $webhook->resourceId);
        $this->assertTrue($webhook->signatureValid);
    }

    public function test_php_normalized_data_id_is_preserved(): void
    {
        $request = Request::create(
            '/webhook?data.id=123456&type=payment',
            'POST'
        );

        $this->assertSame('123456', $request->query('data_id'));

        $webhook = $this->provider->parseWebhook($request);

        $this->assertSame('123456', $webhook->resourceId);
        $this->assertFalse($webhook->signatureValid);
    }

    private function signedRequest(string $dataId): Request
    {
        $requestId = 'request-payment-'.$dataId;
        $timestamp = (string) (time() * 1000);
        $manifest = implode('', [
            'id:',
            $dataId,
            ';request-id:',
            $requestId,
            ';ts:',
            $timestamp,
            ';',
        ]);
        $signature = hash_hmac(
            'sha256',
            $manifest,
            'test-webhook-secret'
        );

        return Request::create(
            '/webhook?data.id='.$dataId.'&type=payment',
            'POST',
            [
                'type' => 'payment',
                'action' => 'payment.updated',
            ],
            server: [
                'HTTP_X_SIGNATURE' => "ts={$timestamp},v1={$signature}",
                'HTTP_X_REQUEST_ID' => $requestId,
            ],
        );
    }
}
