<?php

namespace Tests\Unit\Payment;

use App\Services\Payment\Providers\MercadoPago\MercadoPagoWebhookSignatureVerifier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class MercadoPagoWebhookSignatureVerifierTest extends TestCase
{
    private MercadoPagoWebhookSignatureVerifier $verifier;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.mercadopago.webhook_secret', 'test-secret');
        config()->set('services.mercadopago.webhook_secret_test', null);
        config()->set('services.mercadopago.webhook_secret_production', null);
        config()->set('services.mercadopago.webhook_signature_required', true);
        config()->set(
            'services.mercadopago.webhook_signature_tolerance_seconds',
            300
        );

        $this->verifier = app(MercadoPagoWebhookSignatureVerifier::class);
    }

    public function test_it_accepts_a_valid_signature_with_default_secret(): void
    {
        $request = $this->signedRequest('123456');

        $this->assertTrue($this->verifier->verify($request));
    }

    public function test_it_accepts_a_valid_signature_with_test_secret(): void
    {
        config()->set('services.mercadopago.webhook_secret', null);
        config()->set(
            'services.mercadopago.webhook_secret_test',
            'explicit-test-secret'
        );

        $request = $this->signedRequest(
            '123456',
            secret: 'explicit-test-secret'
        );

        $this->assertTrue($this->verifier->verify($request));
    }

    public function test_it_accepts_a_valid_signature_with_production_secret(): void
    {
        config()->set('services.mercadopago.webhook_secret', null);
        config()->set(
            'services.mercadopago.webhook_secret_production',
            'explicit-production-secret'
        );

        $request = $this->signedRequest(
            '123456',
            secret: 'explicit-production-secret'
        );

        $this->assertTrue($this->verifier->verify($request));
    }

    public function test_it_rejects_a_signature_when_no_secret_matches(): void
    {
        config()->set(
            'services.mercadopago.webhook_secret_test',
            'another-secret'
        );
        config()->set(
            'services.mercadopago.webhook_secret_production',
            'production-secret'
        );
        $request = $this->signedRequest(
            '123456',
            secret: 'unconfigured-secret'
        );

        $this->assertFalse($this->verifier->verify($request));
    }

    public function test_it_rejects_a_missing_signature_header(): void
    {
        $request = $this->signedRequest('123456');
        $request->headers->remove('x-signature');

        $this->assertFalse($this->verifier->verify($request));
    }

    public function test_it_rejects_a_missing_request_id_header(): void
    {
        $request = $this->signedRequest('123456');
        $request->headers->remove('x-request-id');

        $this->assertFalse($this->verifier->verify($request));
    }

    public function test_it_rejects_a_signature_without_timestamp(): void
    {
        $request = $this->signedRequest('123456');
        $request->headers->set('x-signature', 'v1=hash');

        $this->assertFalse($this->verifier->verify($request));
    }

    public function test_it_rejects_a_signature_without_v1(): void
    {
        $request = $this->signedRequest('123456');
        $request->headers->set('x-signature', 'ts='.(string) time());

        $this->assertFalse($this->verifier->verify($request));
    }

    public function test_it_rejects_an_expired_timestamp(): void
    {
        $request = $this->signedRequest(
            '123456',
            timestamp: (string) ((time() - 301) * 1000)
        );

        $this->assertFalse($this->verifier->verify($request));
    }

    public function test_it_normalizes_alphanumeric_data_id_to_lowercase(): void
    {
        $request = $this->signedRequest('ABC123');

        $this->assertTrue($this->verifier->verify($request));
    }

    public function test_it_accepts_php_normalized_data_id_key(): void
    {
        $request = $this->signedRequest('ABC123');

        $this->assertSame('ABC123', $request->query('data_id'));
        $this->assertTrue($this->verifier->verify($request));
    }

    public function test_it_accepts_literal_data_id_key_in_query_bag(): void
    {
        $request = $this->signedRequest('ABC123');
        $request->query->replace(['data.id' => 'ABC123']);

        $this->assertTrue($this->verifier->verify($request));
    }

    public function test_it_accepts_data_id_from_raw_query_string(): void
    {
        $request = $this->signedRequest('ABC123');
        $request->query->replace([]);
        $request->server->set('QUERY_STRING', 'data.id=ABC123&type=payment');

        $this->assertTrue($this->verifier->verify($request));
    }

    public function test_it_logs_only_the_logical_secret_name(): void
    {
        $secret = 'never-log-this-secret-value';
        config()->set('app.debug', true);
        config()->set('services.mercadopago.webhook_secret', null);
        config()->set('services.mercadopago.webhook_secret_test', $secret);
        $logs = [];

        Log::shouldReceive('debug')
            ->andReturnUsing(function (
                string $message,
                array $context
            ) use (&$logs): void {
                $logs[] = [$message, $context];
            });

        $this->assertTrue(
            $this->verifier->verify(
                $this->signedRequest('123456', secret: $secret)
            )
        );

        $matchedLogFound = false;

        foreach ($logs as [$message, $context]) {
            $encodedContext = json_encode($context, JSON_THROW_ON_ERROR);

            $this->assertStringNotContainsString($secret, $encodedContext);

            if (
                $message === 'MercadoPago webhook signature computation'
                && ($context['secret_name'] ?? null) === 'test'
                && ($context['hash_matches'] ?? false) === true
            ) {
                $matchedLogFound = true;
            }
        }

        $this->assertTrue($matchedLogFound);
    }

    public function test_empty_secret_is_invalid_in_production(): void
    {
        config()->set('services.mercadopago.webhook_secret', null);
        config()->set(
            'services.mercadopago.webhook_signature_required',
            false
        );
        app()->detectEnvironment(fn (): string => 'production');

        try {
            $this->assertFalse($this->verifier->verify(Request::create('/')));
        } finally {
            app()->detectEnvironment(fn (): string => 'testing');
        }
    }

    public function test_empty_secret_can_be_explicitly_allowed_locally(): void
    {
        config()->set('services.mercadopago.webhook_secret', null);
        config()->set(
            'services.mercadopago.webhook_signature_required',
            false
        );
        app()->detectEnvironment(fn (): string => 'local');

        try {
            $this->assertTrue($this->verifier->verify(Request::create('/')));
        } finally {
            app()->detectEnvironment(fn (): string => 'testing');
        }
    }

    private function signedRequest(
        string $dataId,
        ?string $signature = null,
        ?string $timestamp = null,
        string $requestId = 'bb56a2f1-6aae-46ac-982e-9dcd3581d08e',
        string $secret = 'test-secret',
    ): Request {
        $timestamp ??= (string) (time() * 1000);
        $manifest = implode('', [
            'id:',
            ctype_alnum($dataId) ? strtolower($dataId) : $dataId,
            ';request-id:',
            $requestId,
            ';ts:',
            $timestamp,
            ';',
        ]);
        $signature ??= hash_hmac('sha256', $manifest, $secret);

        return Request::create(
            '/webhook?data.id='.rawurlencode($dataId),
            'POST',
            server: [
                'HTTP_X_SIGNATURE' => "ts={$timestamp},v1={$signature}",
                'HTTP_X_REQUEST_ID' => $requestId,
            ],
        );
    }
}
