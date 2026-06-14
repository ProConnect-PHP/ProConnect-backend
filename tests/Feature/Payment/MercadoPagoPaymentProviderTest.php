<?php

namespace Tests\Feature\Payment;

use App\Enums\Booking\BookingStatus;
use App\Enums\Payment\PaymentIntentStatus;
use App\Enums\Payment\PaymentProvider;
use App\Enums\Payment\PaymentStatus;
use App\Events\Package\PackagePurchased;
use App\Events\Payment\PaymentSucceeded;
use App\Models\Booking\Booking;
use App\Models\Package\PackageProduct;
use App\Models\Payment\Payment;
use App\Models\Payment\PaymentIntent;
use App\Models\Payment\PaymentWebhookEvent;
use App\Models\Service\Service;
use App\Models\User\ProfessionalProfile;
use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class MercadoPagoPaymentProviderTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_mercadopago_checkout_with_http_fake(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'api.mercadopago.com/checkout/preferences' => Http::response([
                'id' => 'mp_pref_123',
                'init_point' => 'https://www.mercadopago.com/checkout/v1/redirect?pref_id=mp_pref_123',
                'sandbox_init_point' => 'https://sandbox.mercadopago.com/checkout/v1/redirect?pref_id=mp_pref_123',
            ], 201),
        ]);
        $this->configureMercadoPago();

        [$client, $booking] = $this->confirmedBookingScenario();
        $intent = PaymentIntent::factory()
            ->forBooking($booking)
            ->create(['provider_reference' => null]);

        $response = $this
            ->withHeaders($this->authHeaders($client))
            ->postJson("/api/v1/payment-intents/{$intent->id}/checkout", [
                'provider' => PaymentProvider::MercadoPago->value,
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('payment_intent.provider', 'mercadopago')
            ->assertJsonPath('payment_intent.provider_reference', 'mp_pref_123')
            ->assertJsonPath(
                'payment_intent.checkout_url',
                'https://sandbox.mercadopago.com/checkout/v1/redirect?pref_id=mp_pref_123'
            )
            ->assertJsonPath(
                'payment_intent.metadata.provider_environment',
                'sandbox'
            )
            ->assertJsonPath(
                'payment_intent.metadata.init_point',
                'https://www.mercadopago.com/checkout/v1/redirect?pref_id=mp_pref_123'
            )
            ->assertJsonPath(
                'payment_intent.metadata.sandbox_init_point',
                'https://sandbox.mercadopago.com/checkout/v1/redirect?pref_id=mp_pref_123'
            )
            ->assertJsonPath(
                'payment_intent.metadata.external_status',
                'preference_created'
            )
            ->assertJsonPath(
                'payment_intent.status',
                PaymentIntentStatus::CheckoutCreated->value
            );

        Http::assertSent(function ($request) use ($intent): bool {
            return $request->url()
                === 'https://api.mercadopago.com/checkout/preferences'
                && $request->header('X-Idempotency-Key')[0] === $intent->id
                && $request['items'][0]['quantity'] === 1
                && $request['items'][0]['currency_id'] === 'UYU'
                && $request['items'][0]['unit_price'] === 1500.0
                && $request['external_reference'] === $intent->id
                && $request['notification_url']
                    === 'https://proconnect-test.ngrok-free.app/api/v1/payments/webhooks/mercadopago?source_news=webhooks'
                && $request['back_urls']['success']
                    === "http://localhost:4200/payments/success?payment_intent_id={$intent->id}"
                && $request['back_urls']['failure']
                    === "http://localhost:4200/payments/failure?payment_intent_id={$intent->id}"
                && $request['back_urls']['pending']
                    === "http://localhost:4200/payments/pending?payment_intent_id={$intent->id}"
                && ! isset($request['auto_return'])
                && $request['metadata']['payment_intent_id'] === $intent->id;
        });
    }

    public function test_sandbox_checkout_falls_back_to_init_point(): void
    {
        $this->configureMercadoPago();

        $response = $this->createCheckoutWithPreference([
            'id' => 'mp_pref_sandbox_fallback',
            'init_point' => 'https://www.mercadopago.com/checkout/production-fallback',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath(
                'payment_intent.checkout_url',
                'https://www.mercadopago.com/checkout/production-fallback'
            )
            ->assertJsonPath(
                'payment_intent.metadata.provider_environment',
                'sandbox'
            );
    }

    public function test_production_checkout_uses_init_point(): void
    {
        $this->configureMercadoPago('production');

        $response = $this->createCheckoutWithPreference([
            'id' => 'mp_pref_production',
            'init_point' => 'https://www.mercadopago.com/checkout/production',
            'sandbox_init_point' => 'https://sandbox.mercadopago.com/checkout/sandbox',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath(
                'payment_intent.checkout_url',
                'https://www.mercadopago.com/checkout/production'
            )
            ->assertJsonPath(
                'payment_intent.metadata.provider_environment',
                'production'
            );
    }

    public function test_production_checkout_falls_back_to_sandbox_init_point(): void
    {
        $this->configureMercadoPago('production');

        $response = $this->createCheckoutWithPreference([
            'id' => 'mp_pref_production_fallback',
            'sandbox_init_point' => 'https://sandbox.mercadopago.com/checkout/sandbox-fallback',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath(
                'payment_intent.checkout_url',
                'https://sandbox.mercadopago.com/checkout/sandbox-fallback'
            )
            ->assertJsonPath(
                'payment_intent.metadata.provider_environment',
                'production'
            );
    }

    public function test_checkout_sends_auto_return_when_success_url_is_https(): void
    {
        $this->configureMercadoPago();
        config()->set(
            'services.mercadopago.success_url',
            'https://app.proconnect.test/payments/success'
        );

        $this->createCheckoutWithPreference([
            'id' => 'mp_pref_auto_return',
            'sandbox_init_point' => 'https://sandbox.mercadopago.com/checkout/auto-return',
        ])->assertOk();

        Http::assertSent(
            fn ($request): bool => $request->url()
                === 'https://api.mercadopago.com/checkout/preferences'
                && $request['auto_return'] === 'approved'
        );
    }

    public function test_it_processes_mercadopago_webhook_idempotently(): void
    {
        Event::fake([PaymentSucceeded::class]);
        Http::preventStrayRequests();
        $this->configureMercadoPago();

        [$client, $booking] = $this->confirmedBookingScenario();
        $intent = PaymentIntent::factory()
            ->forBooking($booking)
            ->create([
                'provider' => PaymentProvider::MercadoPago,
                'provider_reference' => 'mp_pref_123',
                'status' => PaymentIntentStatus::CheckoutCreated,
            ]);

        Http::fake([
            'api.mercadopago.com/v1/payments/163086264403' => Http::response([
                'id' => '163086264403',
                'status' => 'approved',
                'external_reference' => $intent->id,
                'date_approved' => now()->toAtomString(),
                'transaction_amount' => 1500,
                'currency_id' => 'UYU',
                'payment_type_id' => 'account_money',
                'payment_method_id' => 'account_money',
            ]),
        ]);

        $payload = [
            'id' => 'mp_event_123',
            'type' => 'payment',
            'action' => 'payment.updated',
            'data' => ['id' => '163086264403'],
        ];

        $this->postMercadoPagoWebhook($payload, '163086264403')
            ->assertOk()
            ->assertJson(['received' => true]);
        $this->postMercadoPagoWebhook($payload, '163086264403')
            ->assertOk();

        $this->assertDatabaseCount('payment_webhook_events', 1);
        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'status' => BookingStatus::Paid->value,
        ]);
        $this->assertDatabaseHas('payment_intents', [
            'id' => $intent->id,
            'status' => PaymentIntentStatus::Succeeded->value,
        ]);
        $this->assertDatabaseHas('payments', [
            'payment_intent_id' => $intent->id,
            'provider_payment_id' => '163086264403',
            'status' => PaymentStatus::Succeeded->value,
        ]);
        $payment = Payment::query()
            ->where('payment_intent_id', $intent->id)
            ->firstOrFail();
        $this->assertTrue($payment->metadata['webhook_signature_valid']);
        $this->assertSame(
            'signed_webhook',
            $payment->metadata['verification_mode']
        );
        $this->assertNotEmpty($payment->metadata['webhook_event_id']);
        $this->assertDatabaseHas('payment_webhook_events', [
            'provider_event_id' => 'mp_event_123',
            'signature_valid' => true,
            'status' => 'processed',
        ]);

        Http::assertSentCount(1);
    }

    public function test_invalid_signature_is_processed_when_provider_lookup_is_trusted(): void
    {
        Event::fake([PaymentSucceeded::class]);
        Http::preventStrayRequests();
        $this->configureMercadoPago();
        $this->spyLogAllowingChannels();

        [, $booking] = $this->confirmedBookingScenario();
        $intent = PaymentIntent::factory()
            ->forBooking($booking)
            ->create([
                'provider' => PaymentProvider::MercadoPago,
                'provider_reference' => 'mp_pref_fallback',
                'status' => PaymentIntentStatus::CheckoutCreated,
            ]);

        Http::fake([
            'api.mercadopago.com/v1/payments/163094960575' => Http::response([
                'id' => '163094960575',
                'status' => 'approved',
                'status_detail' => 'accredited',
                'external_reference' => $intent->id,
                'date_approved' => now()->toAtomString(),
                'transaction_amount' => 1500,
                'currency_id' => 'UYU',
                'payment_type_id' => 'credit_card',
                'payment_method_id' => 'visa',
            ]),
        ]);
        $payload = [
            'id' => 'mp_event_fallback',
            'type' => 'payment',
            'action' => 'payment.created',
            'data' => ['id' => '163094960575'],
            'transaction_amount' => 1,
            'currency_id' => 'USD',
            'status' => 'rejected',
        ];

        $this->postInvalidMercadoPagoWebhook($payload, '163094960575')
            ->assertOk()
            ->assertJson(['received' => true]);
        $this->postInvalidMercadoPagoWebhook($payload, '163094960575')
            ->assertOk();

        $this->assertDatabaseCount('payment_webhook_events', 1);
        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseHas('payment_intents', [
            'id' => $intent->id,
            'status' => PaymentIntentStatus::Succeeded->value,
        ]);
        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'status' => BookingStatus::Paid->value,
        ]);
        $this->assertDatabaseHas('payment_webhook_events', [
            'provider_event_id' => 'mp_event_fallback',
            'signature_valid' => false,
            'status' => 'processed',
            'failure_reason' => 'Signature invalid, but payment verified through MercadoPago API.',
        ]);

        $payment = Payment::query()
            ->where('payment_intent_id', $intent->id)
            ->firstOrFail();
        $this->assertSame('163094960575', $payment->provider_payment_id);
        $this->assertSame('approved', $payment->raw_provider_status);
        $this->assertFalse($payment->metadata['webhook_signature_valid']);
        $this->assertSame(
            'provider_verified_after_invalid_signature',
            $payment->metadata['verification_mode']
        );
        $this->assertNotEmpty($payment->metadata['webhook_event_id']);

        Http::assertSentCount(1);
        Log::shouldHaveReceived('warning')
            ->withArgs(fn (string $message, array $context): bool => (
                $message === 'MercadoPago webhook processed through provider verification fallback'
                && $context['resource_id'] === '163094960575'
                && $context['payment_intent_id'] === $intent->id
                && $context['provider_payment_id'] === '163094960575'
                && ! array_key_exists('access_token', $context)
            ))
            ->once();
    }

    public function test_invalid_signature_stays_rejected_when_provider_fetch_fails(): void
    {
        Http::preventStrayRequests();
        $this->configureMercadoPago();
        Http::fake([
            'api.mercadopago.com/v1/payments/163086264404' => Http::response([
                'message' => 'Payment not found',
            ], 404),
        ]);
        [, $booking] = $this->confirmedBookingScenario();
        $intent = PaymentIntent::factory()
            ->forBooking($booking)
            ->create([
                'provider' => PaymentProvider::MercadoPago,
                'provider_reference' => 'mp_pref_invalid',
                'status' => PaymentIntentStatus::CheckoutCreated,
            ]);

        $this->postInvalidMercadoPagoWebhook([
            'id' => 'mp_event_invalid',
            'type' => 'payment',
            'data' => ['id' => '163086264404'],
        ], '163086264404')
            ->assertOk()
            ->assertJson(['received' => true]);

        $this->assertDatabaseHas('payment_webhook_events', [
            'provider' => 'mercadopago',
            'provider_event_id' => 'mp_event_invalid',
            'status' => 'invalid_signature',
            'signature_valid' => false,
        ]);
        $event = PaymentWebhookEvent::query()
            ->where('provider_event_id', 'mp_event_invalid')
            ->firstOrFail();
        $this->assertStringStartsWith(
            'Invalid signature and MercadoPago payment fetch failed:',
            $event->failure_reason
        );
        $this->assertDatabaseHas('payment_intents', [
            'id' => $intent->id,
            'status' => PaymentIntentStatus::CheckoutCreated->value,
        ]);
        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'status' => BookingStatus::Confirmed->value,
        ]);
        $this->assertDatabaseCount('payments', 0);
        Http::assertSentCount(1);
    }

    public function test_invalid_signature_rejects_unmatched_external_reference(): void
    {
        Http::preventStrayRequests();
        $this->configureMercadoPago();
        [, $booking] = $this->confirmedBookingScenario();
        $intent = PaymentIntent::factory()
            ->forBooking($booking)
            ->create([
                'provider' => PaymentProvider::MercadoPago,
                'status' => PaymentIntentStatus::CheckoutCreated,
            ]);
        Http::fake([
            'api.mercadopago.com/v1/payments/163094960576' => Http::response([
                'id' => '163094960576',
                'status' => 'approved',
                'external_reference' => '00000000-0000-0000-0000-000000000000',
                'transaction_amount' => 1500,
                'currency_id' => 'UYU',
            ]),
        ]);

        $this->postInvalidMercadoPagoWebhook([
            'id' => 'mp_event_bad_reference',
            'type' => 'payment',
            'data' => ['id' => '163094960576'],
        ], '163094960576')->assertOk();

        $this->assertDatabaseHas('payment_webhook_events', [
            'provider_event_id' => 'mp_event_bad_reference',
            'status' => 'invalid_signature',
            'failure_reason' => 'Invalid signature and no matching PaymentIntent from provider response.',
        ]);
        $this->assertDomainWasNotPaid($intent, $booking);
    }

    public function test_invalid_signature_rejects_provider_amount_mismatch(): void
    {
        [$intent, $booking] = $this->mercadoPagoFallbackScenario(
            paymentId: '163094960577',
            eventId: 'mp_event_bad_amount',
            providerOverrides: ['transaction_amount' => 1499],
        );

        $this->assertDatabaseHas('payment_webhook_events', [
            'provider_event_id' => 'mp_event_bad_amount',
            'status' => 'invalid_signature',
            'failure_reason' => 'Invalid signature and MercadoPago provider verification mismatch.',
        ]);
        $this->assertDomainWasNotPaid($intent, $booking);
    }

    public function test_invalid_signature_rejects_provider_currency_mismatch(): void
    {
        [$intent, $booking] = $this->mercadoPagoFallbackScenario(
            paymentId: '163094960578',
            eventId: 'mp_event_bad_currency',
            providerOverrides: ['currency_id' => 'USD'],
        );

        $this->assertDatabaseHas('payment_webhook_events', [
            'provider_event_id' => 'mp_event_bad_currency',
            'status' => 'invalid_signature',
            'failure_reason' => 'Invalid signature and MercadoPago provider verification mismatch.',
        ]);
        $this->assertDomainWasNotPaid($intent, $booking);
    }

    public function test_invalid_signature_rejected_payment_does_not_mark_booking_paid(): void
    {
        [$intent, $booking] = $this->mercadoPagoFallbackScenario(
            paymentId: '163094960579',
            eventId: 'mp_event_rejected_fallback',
            providerOverrides: [
                'status' => 'rejected',
                'status_detail' => 'cc_rejected_other_reason',
            ],
        );

        $this->assertDatabaseHas('payment_webhook_events', [
            'provider_event_id' => 'mp_event_rejected_fallback',
            'signature_valid' => false,
            'status' => 'processed',
            'failure_reason' => 'Signature invalid, but payment verified through MercadoPago API.',
        ]);
        $this->assertDatabaseHas('payment_intents', [
            'id' => $intent->id,
            'status' => PaymentIntentStatus::Failed->value,
        ]);
        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'status' => BookingStatus::Confirmed->value,
        ]);
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_invalid_signature_cannot_replace_payment_id_on_succeeded_intent(): void
    {
        Event::fake([PaymentSucceeded::class]);
        Http::preventStrayRequests();
        $this->configureMercadoPago();
        [, $booking] = $this->confirmedBookingScenario();
        $intent = PaymentIntent::factory()
            ->forBooking($booking)
            ->create([
                'provider' => PaymentProvider::MercadoPago,
                'status' => PaymentIntentStatus::CheckoutCreated,
            ]);
        Http::fake([
            'api.mercadopago.com/v1/payments/163094960580' => Http::response([
                'id' => '163094960580',
                'status' => 'approved',
                'external_reference' => $intent->id,
                'transaction_amount' => 1500,
                'currency_id' => 'UYU',
            ]),
            'api.mercadopago.com/v1/payments/163094960581' => Http::response([
                'id' => '163094960581',
                'status' => 'approved',
                'external_reference' => $intent->id,
                'transaction_amount' => 1500,
                'currency_id' => 'UYU',
            ]),
        ]);

        $this->postMercadoPagoWebhook([
            'id' => 'mp_event_original_payment',
            'type' => 'payment',
            'data' => ['id' => '163094960580'],
        ], '163094960580')->assertOk();
        $this->postInvalidMercadoPagoWebhook([
            'id' => 'mp_event_replacement_payment',
            'type' => 'payment',
            'data' => ['id' => '163094960581'],
        ], '163094960581')->assertOk();

        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseHas('payments', [
            'payment_intent_id' => $intent->id,
            'provider_payment_id' => '163094960580',
        ]);
        $this->assertDatabaseHas('payment_webhook_events', [
            'provider_event_id' => 'mp_event_replacement_payment',
            'status' => 'invalid_signature',
            'failure_reason' => 'Invalid signature and MercadoPago provider verification mismatch.',
        ]);
    }

    public function test_it_ignores_non_payment_webhook_without_verifying_signature(): void
    {
        Http::preventStrayRequests();
        $this->configureMercadoPago();
        [, $booking] = $this->confirmedBookingScenario();
        $intent = PaymentIntent::factory()
            ->forBooking($booking)
            ->create([
                'provider' => PaymentProvider::MercadoPago,
                'provider_reference' => 'mp_pref_merchant_order',
                'status' => PaymentIntentStatus::CheckoutCreated,
            ]);
        $this->postJson(
            '/api/v1/payments/webhooks/mercadopago'
                .'?data.id=41788606511&type=topic_merchant_order_wh',
            [
                'id' => '41788606511',
                'action' => 'create',
                'data' => ['id' => '41788606511'],
            ]
        )
            ->assertOk()
            ->assertJson(['received' => true]);

        $event = PaymentWebhookEvent::query()
            ->where('provider_event_id', '41788606511')
            ->firstOrFail();

        $this->assertSame('ignored', $event->status->value);
        $this->assertTrue($event->signature_valid);
        $this->assertSame(
            'Unsupported MercadoPago webhook resource type.',
            $event->failure_reason
        );
        $this->assertNotNull($event->processed_at);
        $this->assertDatabaseHas('payment_intents', [
            'id' => $intent->id,
            'status' => PaymentIntentStatus::CheckoutCreated->value,
        ]);
        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'status' => BookingStatus::Confirmed->value,
        ]);
        $this->assertDatabaseCount('payments', 0);
        Http::assertNothingSent();
    }

    public function test_it_ignores_payment_with_non_numeric_id_without_verifying_signature(): void
    {
        Http::preventStrayRequests();
        $this->configureMercadoPago();
        [, $booking] = $this->confirmedBookingScenario();
        $intent = PaymentIntent::factory()
            ->forBooking($booking)
            ->create([
                'provider' => PaymentProvider::MercadoPago,
                'provider_reference' => 'mp_pref_non_numeric',
                'status' => PaymentIntentStatus::CheckoutCreated,
            ]);

        $this->postJson(
            '/api/v1/payments/webhooks/mercadopago'
                .'?data.id=abc&type=payment',
            [
                'id' => 'mp_event_non_numeric',
                'type' => 'payment',
                'data' => ['id' => 'abc'],
            ]
        )
            ->assertOk()
            ->assertJson(['received' => true]);

        $this->assertDatabaseHas('payment_webhook_events', [
            'provider_event_id' => 'mp_event_non_numeric',
            'resource_type' => 'payment',
            'resource_id' => 'abc',
            'signature_valid' => true,
            'status' => 'ignored',
            'failure_reason' => 'Invalid MercadoPago payment id.',
        ]);
        $this->assertDatabaseHas('payment_intents', [
            'id' => $intent->id,
            'status' => PaymentIntentStatus::CheckoutCreated->value,
        ]);
        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'status' => BookingStatus::Confirmed->value,
        ]);
        $this->assertDatabaseCount('payments', 0);
        Http::assertNothingSent();
    }

    public function test_panel_simulation_is_persisted_as_failed_when_payment_is_missing(): void
    {
        Http::preventStrayRequests();
        $this->configureMercadoPago();
        Http::fake([
            'api.mercadopago.com/v1/payments/123456' => Http::response([
                'message' => 'Payment not found',
            ], 404),
        ]);

        $this->postMercadoPagoWebhook([
            'id' => '123456',
            'type' => 'payment',
            'action' => 'payment.updated',
            'data' => ['id' => '123456'],
        ], '123456')
            ->assertStatus(502)
            ->assertJsonPath(
                'error.type',
                'MercadoPagoPaymentLookupFailed'
            );

        $this->assertDatabaseHas('payment_webhook_events', [
            'provider_event_id' => '123456',
            'resource_type' => 'payment',
            'signature_valid' => true,
            'status' => 'failed',
            'failure_reason' => 'No se pudo verificar el pago de MercadoPago.',
        ]);
        $this->assertDatabaseCount('payments', 0);
        Http::assertSentCount(1);
    }

    public function test_valid_retry_can_process_an_event_previously_rejected_for_signature(): void
    {
        Event::fake([PaymentSucceeded::class]);
        Http::preventStrayRequests();
        $this->configureMercadoPago();
        [, $booking] = $this->confirmedBookingScenario();
        $intent = PaymentIntent::factory()
            ->forBooking($booking)
            ->create([
                'provider' => PaymentProvider::MercadoPago,
                'provider_reference' => 'mp_pref_retry',
                'status' => PaymentIntentStatus::CheckoutCreated,
            ]);
        $payload = [
            'id' => 'mp_event_retry',
            'type' => 'payment',
            'action' => 'payment.updated',
            'data' => ['id' => '163086264405'],
        ];

        $this
            ->withHeaders([
                'x-signature' => 'ts='.(string) time().',v1=invalid',
                'x-request-id' => 'request-invalid-retry',
            ])
            ->postJson(
                '/api/v1/payments/webhooks/mercadopago?data.id=163086264405&type=payment',
                $payload
            )
            ->assertOk();

        Http::fake([
            'api.mercadopago.com/v1/payments/163086264405' => Http::response([
                'id' => '163086264405',
                'status' => 'approved',
                'external_reference' => $intent->id,
                'transaction_amount' => 1500,
                'currency_id' => 'UYU',
            ]),
        ]);

        $this->postMercadoPagoWebhook($payload, '163086264405')
            ->assertOk();

        $this->assertDatabaseCount('payment_webhook_events', 1);
        $this->assertDatabaseHas('payment_webhook_events', [
            'provider_event_id' => 'mp_event_retry',
            'signature_valid' => true,
            'status' => 'processed',
        ]);
        $this->assertDatabaseHas('payments', [
            'payment_intent_id' => $intent->id,
            'provider_payment_id' => '163086264405',
        ]);
    }

    public function test_it_rejects_provider_amount_mismatch(): void
    {
        Event::fake([PaymentSucceeded::class]);
        Http::preventStrayRequests();
        $this->configureMercadoPago();
        [, $booking] = $this->confirmedBookingScenario();
        $intent = PaymentIntent::factory()
            ->forBooking($booking)
            ->create([
                'provider' => PaymentProvider::MercadoPago,
                'provider_reference' => 'mp_pref_mismatch',
                'status' => PaymentIntentStatus::CheckoutCreated,
            ]);
        Http::fake([
            'api.mercadopago.com/v1/payments/163086264406' => Http::response([
                'id' => '163086264406',
                'status' => 'approved',
                'external_reference' => $intent->id,
                'transaction_amount' => 1,
                'currency_id' => 'UYU',
            ]),
        ]);

        $this->postMercadoPagoWebhook([
            'id' => 'mp_event_mismatch',
            'type' => 'payment',
            'action' => 'payment.updated',
            'data' => ['id' => '163086264406'],
        ], '163086264406')
            ->assertConflict()
            ->assertJsonPath('error.type', 'ProviderPaymentAmountMismatch');

        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'status' => BookingStatus::Confirmed->value,
        ]);
        $this->assertDatabaseHas('payment_webhook_events', [
            'provider_event_id' => 'mp_event_mismatch',
            'status' => 'failed',
        ]);
    }

    public function test_pending_payment_marks_intent_as_processing(): void
    {
        Http::preventStrayRequests();
        $this->configureMercadoPago();
        [, $booking] = $this->confirmedBookingScenario();
        $intent = PaymentIntent::factory()
            ->forBooking($booking)
            ->create([
                'provider' => PaymentProvider::MercadoPago,
                'provider_reference' => 'mp_pref_pending',
                'status' => PaymentIntentStatus::CheckoutCreated,
            ]);
        Http::fake([
            'api.mercadopago.com/v1/payments/163086264407' => Http::response([
                'id' => '163086264407',
                'status' => 'pending',
                'metadata' => ['payment_intent_id' => $intent->id],
            ]),
        ]);

        $this->postMercadoPagoWebhook([
            'id' => 'mp_event_pending',
            'type' => 'payment',
            'action' => 'payment.updated',
            'data' => ['id' => '163086264407'],
        ], '163086264407')->assertOk();

        $this->assertDatabaseHas('payment_intents', [
            'id' => $intent->id,
            'status' => PaymentIntentStatus::Processing->value,
        ]);
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_rejected_payment_marks_intent_as_failed(): void
    {
        Http::preventStrayRequests();
        $this->configureMercadoPago();
        [, $booking] = $this->confirmedBookingScenario();
        $intent = PaymentIntent::factory()
            ->forBooking($booking)
            ->create([
                'provider' => PaymentProvider::MercadoPago,
                'provider_reference' => 'mp_pref_rejected',
                'status' => PaymentIntentStatus::CheckoutCreated,
            ]);
        Http::fake([
            'api.mercadopago.com/v1/payments/163086264408' => Http::response([
                'id' => '163086264408',
                'status' => 'rejected',
                'external_reference' => $intent->id,
            ]),
        ]);

        $this->postMercadoPagoWebhook([
            'id' => 'mp_event_rejected',
            'type' => 'payment',
            'action' => 'payment.updated',
            'data' => ['id' => '163086264408'],
        ], '163086264408')->assertOk();

        $this->assertDatabaseHas('payment_intents', [
            'id' => $intent->id,
            'status' => PaymentIntentStatus::Failed->value,
        ]);
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_approved_package_payment_is_finalized_once(): void
    {
        Event::fake([PaymentSucceeded::class, PackagePurchased::class]);
        Http::preventStrayRequests();
        $this->configureMercadoPago();
        $client = User::factory()->create();
        $package = PackageProduct::factory()->active()->create([
            'price' => 5600,
            'currency' => 'UYU',
            'sessions_count' => 4,
        ]);
        $intent = PaymentIntent::factory()
            ->forPackageProduct($package, $client)
            ->create([
                'provider' => PaymentProvider::MercadoPago,
                'provider_reference' => 'mp_pref_package',
                'status' => PaymentIntentStatus::CheckoutCreated,
            ]);
        Http::fake([
            'api.mercadopago.com/v1/payments/163086264409' => Http::response([
                'id' => '163086264409',
                'status' => 'approved',
                'external_reference' => $intent->id,
                'transaction_amount' => 5600,
                'currency_id' => 'UYU',
            ]),
        ]);
        $payload = [
            'id' => 'mp_event_package',
            'type' => 'payment',
            'action' => 'payment.updated',
            'data' => ['id' => '163086264409'],
        ];

        $this->postMercadoPagoWebhook($payload, '163086264409')
            ->assertOk();
        $this->postMercadoPagoWebhook($payload, '163086264409')
            ->assertOk();

        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseCount('client_packages', 1);
        $this->assertDatabaseHas('payments', [
            'payment_intent_id' => $intent->id,
            'provider_payment_id' => '163086264409',
            'status' => PaymentStatus::Succeeded->value,
        ]);
        Http::assertSentCount(1);
    }

    public function test_payment_without_intent_reference_is_persisted_as_unmatched(): void
    {
        Http::preventStrayRequests();
        $this->configureMercadoPago();
        Http::fake([
            'api.mercadopago.com/v1/payments/163086264410' => Http::response([
                'id' => '163086264410',
                'status' => 'approved',
                'transaction_amount' => 1500,
                'currency_id' => 'UYU',
            ]),
        ]);

        $this->postMercadoPagoWebhook([
            'id' => 'mp_event_unmatched',
            'type' => 'payment',
            'action' => 'payment.updated',
            'data' => ['id' => '163086264410'],
        ], '163086264410')
            ->assertNotFound()
            ->assertJsonPath('error.type', 'PaymentIntentNotFound');

        $this->assertDatabaseHas('payment_webhook_events', [
            'provider_event_id' => 'mp_event_unmatched',
            'status' => 'failed',
        ]);
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_checkout_rejects_a_local_notification_url(): void
    {
        Http::preventStrayRequests();
        $this->configureMercadoPago();
        config()->set(
            'services.mercadopago.notification_url',
            'http://localhost/api/v1/payments/webhooks/mercadopago'
        );
        [$client, $booking] = $this->confirmedBookingScenario();
        $intent = PaymentIntent::factory()
            ->forBooking($booking)
            ->create(['provider_reference' => null]);

        $this
            ->withHeaders($this->authHeaders($client))
            ->postJson("/api/v1/payment-intents/{$intent->id}/checkout", [
                'provider' => PaymentProvider::MercadoPago->value,
            ])
            ->assertServiceUnavailable()
            ->assertJsonPath(
                'error.type',
                'MercadoPagoNotificationUrlInvalid'
            );

        Http::assertNothingSent();
    }

    public function test_checkout_failure_logs_and_returns_provider_body(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'api.mercadopago.com/checkout/preferences' => Http::response([
                'message' => 'invalid notification_url',
                'cause' => [[
                    'code' => 'invalid_notification_url',
                    'description' => 'notification_url must be public',
                ]],
            ], 400),
        ]);
        $this->configureMercadoPago();
        $this->spyLogAllowingChannels();
        [$client, $booking] = $this->confirmedBookingScenario();
        $intent = PaymentIntent::factory()
            ->forBooking($booking)
            ->create(['provider_reference' => null]);

        $this
            ->withHeaders($this->authHeaders($client))
            ->postJson("/api/v1/payment-intents/{$intent->id}/checkout", [
                'provider' => PaymentProvider::MercadoPago->value,
            ])
            ->assertStatus(502)
            ->assertJsonPath(
                'error.details.provider_body.cause.0.code',
                'invalid_notification_url'
            );

        Log::shouldHaveReceived('error')
            ->withArgs(fn (string $message, array $context): bool => (
                $message === 'MercadoPago create preference failed'
                && (
                    $context['payment_intent_id'] === $intent->id
                    && $context['provider_status'] === 400
                    && data_get(
                        $context,
                        'provider_response.cause.0.code'
                    ) === 'invalid_notification_url'
                    && str_contains(
                        $context['provider_raw_body'],
                        'invalid_notification_url'
                    )
                    && ! array_key_exists('access_token', $context)
                )
            ))
            ->once();
    }

    private function confirmedBookingScenario(): array
    {
        $client = User::factory()->create();
        $professional = ProfessionalProfile::factory()->create();
        $service = Service::factory()->create([
            'professional_id' => $professional->id,
            'price' => 1500,
            'duration_minutes' => 60,
            'modality' => 'remota',
        ]);
        $booking = Booking::factory()->create([
            'service_id' => $service->id,
            'professional_id' => $professional->id,
            'client_id' => $client->id,
            'status' => BookingStatus::Confirmed,
            'confirmed_at' => now(),
            'price_snapshot' => 1500,
            'duration_minutes_snapshot' => 60,
            'modality' => 'remota',
        ]);

        return [$client, $booking];
    }

    private function authHeaders(User $user): array
    {
        return [
            'Authorization' => 'Bearer '.auth('user_jwt')->login($user),
            'Accept' => 'application/json',
        ];
    }

    private function configureMercadoPago(string $mode = 'sandbox'): void
    {
        config()->set('services.mercadopago.mode', $mode);
        config()->set(
            'services.mercadopago.access_token',
            'test-access-token'
        );
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
        config()->set(
            'services.mercadopago.notification_url',
            'https://proconnect-test.ngrok-free.app/api/v1/payments/webhooks/mercadopago'
        );
        config()->set(
            'services.mercadopago.success_url',
            'http://localhost:4200/payments/success'
        );
        config()->set(
            'services.mercadopago.failure_url',
            'http://localhost:4200/payments/failure'
        );
        config()->set(
            'services.mercadopago.pending_url',
            'http://localhost:4200/payments/pending'
        );
    }

    private function createCheckoutWithPreference(array $preference)
    {
        Http::preventStrayRequests();
        Http::fake([
            'api.mercadopago.com/checkout/preferences' => Http::response(
                $preference,
                201
            ),
        ]);
        [$client, $booking] = $this->confirmedBookingScenario();
        $intent = PaymentIntent::factory()
            ->forBooking($booking)
            ->create(['provider_reference' => null]);

        return $this
            ->withHeaders($this->authHeaders($client))
            ->postJson("/api/v1/payment-intents/{$intent->id}/checkout", [
                'provider' => PaymentProvider::MercadoPago->value,
            ]);
    }

    /**
     * @return array{0: PaymentIntent, 1: Booking}
     */
    private function mercadoPagoFallbackScenario(
        string $paymentId,
        string $eventId,
        array $providerOverrides = [],
    ): array {
        Http::preventStrayRequests();
        $this->configureMercadoPago();
        [, $booking] = $this->confirmedBookingScenario();
        $intent = PaymentIntent::factory()
            ->forBooking($booking)
            ->create([
                'provider' => PaymentProvider::MercadoPago,
                'provider_reference' => 'mp_pref_'.$eventId,
                'status' => PaymentIntentStatus::CheckoutCreated,
            ]);
        Http::fake([
            "api.mercadopago.com/v1/payments/{$paymentId}" => Http::response([
                ...[
                    'id' => $paymentId,
                    'status' => 'approved',
                    'external_reference' => $intent->id,
                    'transaction_amount' => 1500,
                    'currency_id' => 'UYU',
                ],
                ...$providerOverrides,
            ]),
        ]);

        $this->postInvalidMercadoPagoWebhook([
            'id' => $eventId,
            'type' => 'payment',
            'action' => 'payment.updated',
            'data' => ['id' => $paymentId],
        ], $paymentId)
            ->assertOk()
            ->assertJson(['received' => true]);

        return [$intent, $booking];
    }

    private function assertDomainWasNotPaid(
        PaymentIntent $intent,
        Booking $booking
    ): void {
        $this->assertDatabaseHas('payment_intents', [
            'id' => $intent->id,
            'status' => PaymentIntentStatus::CheckoutCreated->value,
        ]);
        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'status' => BookingStatus::Confirmed->value,
        ]);
        $this->assertDatabaseCount('payments', 0);
    }

    private function postInvalidMercadoPagoWebhook(
        array $payload,
        string $paymentId
    ) {
        return $this
            ->withHeaders([
                'x-signature' => 'ts='.(string) time().',v1=invalid',
                'x-request-id' => 'request-invalid-'.$payload['id'],
            ])
            ->postJson(
                '/api/v1/payments/webhooks/mercadopago?data.id='
                    .rawurlencode($paymentId)
                    .'&type=payment',
                $payload
            );
    }

    private function postMercadoPagoWebhook(
        array $payload,
        string $paymentId
    ) {
        $requestId = 'request-'.$payload['id'];
        $timestamp = (string) (time() * 1000);
        $manifest = implode('', [
            'id:',
            ctype_alnum($paymentId) ? strtolower($paymentId) : $paymentId,
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

        return $this
            ->withHeaders([
                'x-signature' => "ts={$timestamp},v1={$signature}",
                'x-request-id' => $requestId,
            ])
            ->postJson(
                '/api/v1/payments/webhooks/mercadopago?data.id='
                    .rawurlencode($paymentId)
                    .'&type=payment',
                $payload
            );
    }

    private function spyLogAllowingChannels(): void
    {
        Log::spy();

        Log::shouldReceive('channel')
            ->andReturnSelf()
            ->byDefault();

        Log::shouldReceive('stack')
            ->andReturnSelf()
            ->byDefault();

        Log::shouldReceive('driver')
            ->andReturnSelf()
            ->byDefault();
    }
}
