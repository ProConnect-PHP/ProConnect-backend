<?php

namespace Tests\Feature\Payment;

use App\Enums\Booking\BookingStatus;
use App\Enums\Payment\PaymentIntentStatus;
use App\Enums\Payment\PaymentProvider;
use App\Events\Payment\PaymentSucceeded;
use App\Models\Booking\Booking;
use App\Models\Payment\PaymentIntent;
use App\Models\Service\Service;
use App\Models\User\ProfessionalProfile;
use App\Models\User\User;
use App\Services\Payment\PaymentProviderManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PayPalPaymentProviderTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_paypal_checkout_with_http_fake(): void
    {
        $this->configurePayPal();
        Http::preventStrayRequests();
        Http::fake([
            'api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response([
                'access_token' => 'fake-token',
                'expires_in' => 3600,
            ]),
            'api-m.sandbox.paypal.com/v2/checkout/orders' => Http::response([
                'id' => 'PAYPAL_ORDER_123',
                'status' => 'CREATED',
                'links' => [[
                    'href' => 'https://www.sandbox.paypal.com/checkoutnow?token=PAYPAL_ORDER_123',
                    'rel' => 'payer-action',
                    'method' => 'GET',
                ]],
            ], 201),
        ]);

        [$client, $booking] = $this->confirmedBookingScenario();
        $intent = PaymentIntent::factory()
            ->forBooking($booking)
            ->create(['provider_reference' => null]);

        $response = $this
            ->withHeaders($this->authHeaders($client))
            ->postJson("/api/v1/payment-intents/{$intent->id}/checkout", [
                'provider' => PaymentProvider::PayPal->value,
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('payment_intent.provider', 'paypal')
            ->assertJsonPath(
                'payment_intent.provider_reference',
                'PAYPAL_ORDER_123'
            )
            ->assertJsonPath(
                'payment_intent.checkout_url',
                'https://www.sandbox.paypal.com/checkoutnow?token=PAYPAL_ORDER_123'
            )
            ->assertJsonPath(
                'payment_intent.metadata.paypal_order_id',
                'PAYPAL_ORDER_123'
            )
            ->assertJsonPath(
                'payment_intent.metadata.paypal_custom_id',
                $intent->id
            );

        $this->assertDatabaseHas('payment_intents', [
            'id' => $intent->id,
            'provider_reference' => 'PAYPAL_ORDER_123',
        ]);
    }

    public function test_it_verifies_and_processes_paypal_webhook(): void
    {
        Event::fake([PaymentSucceeded::class]);
        $this->configurePayPal();
        Http::preventStrayRequests();

        [$client, $booking] = $this->confirmedBookingScenario();
        $intent = PaymentIntent::factory()
            ->forBooking($booking)
            ->create([
                'provider' => PaymentProvider::PayPal,
                'provider_reference' => 'PAYPAL_ORDER_123',
                'status' => PaymentIntentStatus::CheckoutCreated,
            ]);

        Http::fake([
            'api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response([
                'access_token' => 'fake-token',
                'expires_in' => 3600,
            ]),
            'api-m.sandbox.paypal.com/v1/notifications/verify-webhook-signature' => Http::response([
                'verification_status' => 'SUCCESS',
            ]),
            'api-m.sandbox.paypal.com/v2/checkout/orders/PAYPAL_ORDER_123' => Http::response([
                'id' => 'PAYPAL_ORDER_123',
                'status' => 'COMPLETED',
                'update_time' => now()->toAtomString(),
                'purchase_units' => [[
                    'reference_id' => $intent->id,
                    'payments' => [
                        'captures' => [[
                            'id' => 'PAYPAL_CAPTURE_123',
                            'status' => 'COMPLETED',
                            'update_time' => now()->toAtomString(),
                            'amount' => [
                                'value' => '37.50',
                                'currency_code' => 'USD',
                            ],
                        ]],
                    ],
                ]],
            ]),
        ]);

        $payload = [
            'id' => 'PAYPAL_EVENT_123',
            'event_type' => 'PAYMENT.CAPTURE.COMPLETED',
            'resource_type' => 'capture',
            'resource' => [
                'id' => 'PAYPAL_CAPTURE_123',
                'status' => 'COMPLETED',
                'supplementary_data' => [
                    'related_ids' => [
                        'order_id' => 'PAYPAL_ORDER_123',
                    ],
                ],
            ],
        ];

        $this->postSignedPayPalWebhook($payload)
            ->assertOk()
            ->assertJson(['received' => true]);
        $this->postSignedPayPalWebhook($payload)->assertOk();

        $this->assertDatabaseHas('payments', [
            'payment_intent_id' => $intent->id,
            'provider' => 'paypal',
            'provider_payment_id' => 'PAYPAL_CAPTURE_123',
            'raw_provider_status' => 'COMPLETED',
        ]);
        $this->assertDatabaseHas('payment_webhook_events', [
            'provider_event_id' => 'PAYPAL_EVENT_123',
            'payment_intent_id' => $intent->id,
            'signature_valid' => true,
            'status' => 'processed',
        ]);
        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'status' => BookingStatus::Paid->value,
        ]);
        $this->assertDatabaseCount('payments', 1);
    }

    public function test_it_captures_an_approved_paypal_order_server_to_server(): void
    {
        $this->configurePayPal();
        Http::preventStrayRequests();
        Http::fake([
            'api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response([
                'access_token' => 'fake-token',
                'expires_in' => 3600,
            ]),
            'api-m.sandbox.paypal.com/v2/checkout/orders/PAYPAL_ORDER_APPROVED' => Http::response([
                'id' => 'PAYPAL_ORDER_APPROVED',
                'status' => 'APPROVED',
                'purchase_units' => [[
                    'reference_id' => 'intent-id',
                    'amount' => [
                        'value' => '37.50',
                        'currency_code' => 'USD',
                    ],
                ]],
            ]),
            'api-m.sandbox.paypal.com/v2/checkout/orders/PAYPAL_ORDER_APPROVED/capture' => Http::response([
                'id' => 'PAYPAL_ORDER_APPROVED',
                'status' => 'COMPLETED',
                'purchase_units' => [[
                    'reference_id' => 'intent-id',
                    'payments' => [
                        'captures' => [[
                            'id' => 'PAYPAL_CAPTURE_APPROVED',
                            'status' => 'COMPLETED',
                            'amount' => [
                                'value' => '37.50',
                                'currency_code' => 'USD',
                            ],
                        ]],
                    ],
                ]],
            ]),
        ]);

        $status = app(PaymentProviderManager::class)
            ->driver(PaymentProvider::PayPal)
            ->fetchPaymentStatus('PAYPAL_ORDER_APPROVED');

        $this->assertSame('succeeded', $status->status->value);
        $this->assertSame('PAYPAL_CAPTURE_APPROVED', $status->providerPaymentId);
        Http::assertSent(
            fn ($request): bool => $request->url()
                === 'https://api-m.sandbox.paypal.com/v2/checkout/orders/PAYPAL_ORDER_APPROVED/capture'
                && $request->body() === '{}'
        );
    }

    public function test_invalid_paypal_signature_does_not_use_provider_fallback(): void
    {
        $this->configurePayPal();
        Http::preventStrayRequests();
        Http::fake([
            'api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response([
                'access_token' => 'fake-token',
                'expires_in' => 3600,
            ]),
            'api-m.sandbox.paypal.com/v1/notifications/verify-webhook-signature' => Http::response([
                'verification_status' => 'FAILURE',
            ]),
        ]);
        [, $booking] = $this->confirmedBookingScenario();
        $intent = PaymentIntent::factory()
            ->forBooking($booking)
            ->create([
                'provider' => PaymentProvider::PayPal,
                'provider_reference' => 'PAYPAL_ORDER_INVALID',
                'status' => PaymentIntentStatus::CheckoutCreated,
            ]);

        $this
            ->withHeaders([
                'PAYPAL-AUTH-ALGO' => 'SHA256withRSA',
                'PAYPAL-CERT-URL' => 'https://api-m.paypal.com/cert.pem',
                'PAYPAL-TRANSMISSION-ID' => 'transmission-invalid',
                'PAYPAL-TRANSMISSION-SIG' => 'signature-invalid',
                'PAYPAL-TRANSMISSION-TIME' => now()->toAtomString(),
            ])
            ->postJson('/api/v1/payments/webhooks/paypal', [
                'id' => 'PAYPAL_EVENT_INVALID',
                'event_type' => 'PAYMENT.CAPTURE.COMPLETED',
                'resource_type' => 'capture',
                'resource' => [
                    'id' => 'PAYPAL_CAPTURE_INVALID',
                    'supplementary_data' => [
                        'related_ids' => [
                            'order_id' => 'PAYPAL_ORDER_INVALID',
                        ],
                    ],
                ],
            ])
            ->assertOk();

        $this->assertDatabaseHas('payment_webhook_events', [
            'provider_event_id' => 'PAYPAL_EVENT_INVALID',
            'signature_valid' => false,
            'status' => 'invalid_signature',
        ]);
        $this->assertDatabaseHas('payment_intents', [
            'id' => $intent->id,
            'status' => PaymentIntentStatus::CheckoutCreated->value,
        ]);
        $this->assertDatabaseCount('payments', 0);
        Http::assertSentCount(2);
        Http::assertNotSent(
            fn ($request): bool => str_contains(
                $request->url(),
                '/v2/checkout/orders/PAYPAL_ORDER_INVALID'
            )
        );
    }

    public function test_capture_endpoint_marks_an_approved_order_as_paid_once(): void
    {
        Event::fake([PaymentSucceeded::class]);
        $this->configurePayPal();
        Http::preventStrayRequests();

        [$client, $booking] = $this->confirmedBookingScenario();
        $intent = PaymentIntent::factory()
            ->forBooking($booking)
            ->create([
                'provider' => PaymentProvider::PayPal,
                'provider_reference' => 'PAYPAL_ORDER_CAPTURED',
                'status' => PaymentIntentStatus::CheckoutCreated,
            ]);
        $this->fakePayPalCapture(
            orderId: 'PAYPAL_ORDER_CAPTURED',
            paymentIntentId: $intent->id,
            captureStatus: 'COMPLETED',
        );

        $response = $this
            ->withHeaders($this->authHeaders($client))
            ->postJson("/api/v1/payment-intents/{$intent->id}/capture");

        $response
            ->assertOk()
            ->assertJsonPath('payment_intent.status', 'succeeded')
            ->assertJsonPath('payment.provider_payment_id', 'PAYPAL_CAPTURE_COMPLETED')
            ->assertJsonPath('status', 'succeeded')
            ->assertJsonPath('is_final', true);
        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'status' => BookingStatus::Paid->value,
        ]);

        $this
            ->withHeaders($this->authHeaders($client))
            ->postJson("/api/v1/payment-intents/{$intent->id}/capture")
            ->assertOk()
            ->assertJsonPath('payment_intent.status', 'succeeded');

        $this->assertDatabaseCount('payments', 1);
        Http::assertSentCount(4);
    }

    public function test_capture_endpoint_marks_pending_capture_as_processing(): void
    {
        $this->configurePayPal();
        Http::preventStrayRequests();

        [$client, $booking] = $this->confirmedBookingScenario();
        $intent = PaymentIntent::factory()
            ->forBooking($booking)
            ->create([
                'provider' => PaymentProvider::PayPal,
                'provider_reference' => 'PAYPAL_ORDER_PENDING',
                'status' => PaymentIntentStatus::CheckoutCreated,
            ]);
        $this->fakePayPalCapture(
            orderId: 'PAYPAL_ORDER_PENDING',
            paymentIntentId: $intent->id,
            captureStatus: 'PENDING',
        );

        $this
            ->withHeaders($this->authHeaders($client))
            ->postJson("/api/v1/payment-intents/{$intent->id}/capture")
            ->assertOk()
            ->assertJsonPath('payment_intent.status', 'processing')
            ->assertJsonPath('status', 'processing')
            ->assertJsonPath('next_poll_after_seconds', 5);

        $this->assertDatabaseCount('payments', 0);
    }

    public function test_capture_endpoint_marks_denied_capture_as_failed(): void
    {
        $this->configurePayPal();
        Http::preventStrayRequests();

        [$client, $booking] = $this->confirmedBookingScenario();
        $intent = PaymentIntent::factory()
            ->forBooking($booking)
            ->create([
                'provider' => PaymentProvider::PayPal,
                'provider_reference' => 'PAYPAL_ORDER_DENIED',
                'status' => PaymentIntentStatus::CheckoutCreated,
            ]);
        $this->fakePayPalCapture(
            orderId: 'PAYPAL_ORDER_DENIED',
            paymentIntentId: $intent->id,
            captureStatus: 'DENIED',
        );

        $this
            ->withHeaders($this->authHeaders($client))
            ->postJson("/api/v1/payment-intents/{$intent->id}/capture")
            ->assertOk()
            ->assertJsonPath('payment_intent.status', 'failed');

        $this->assertDatabaseCount('payments', 0);
    }

    public function test_checkout_order_approved_webhook_captures_and_is_idempotent(): void
    {
        Event::fake([PaymentSucceeded::class]);
        $this->configurePayPal();
        Http::preventStrayRequests();

        [, $booking] = $this->confirmedBookingScenario();
        $intent = PaymentIntent::factory()
            ->forBooking($booking)
            ->create([
                'provider' => PaymentProvider::PayPal,
                'provider_reference' => 'PAYPAL_ORDER_APPROVED_WEBHOOK',
                'status' => PaymentIntentStatus::CheckoutCreated,
            ]);
        Http::fake([
            ...$this->paypalWebhookSignatureResponses(),
            ...$this->paypalCaptureResponses(
                orderId: 'PAYPAL_ORDER_APPROVED_WEBHOOK',
                paymentIntentId: $intent->id,
                captureStatus: 'COMPLETED',
            ),
        ]);

        $payload = [
            'id' => 'PAYPAL_EVENT_APPROVED_ONCE',
            'event_type' => 'CHECKOUT.ORDER.APPROVED',
            'resource_type' => 'checkout-order',
            'resource' => [
                'id' => 'PAYPAL_ORDER_APPROVED_WEBHOOK',
                'status' => 'APPROVED',
                'purchase_units' => [[
                    'reference_id' => $intent->id,
                    'custom_id' => $intent->id,
                    'amount' => [
                        'value' => '37.50',
                        'currency_code' => 'USD',
                    ],
                ]],
            ],
        ];

        $this->postSignedPayPalWebhook($payload)->assertOk();
        $this->postSignedPayPalWebhook($payload)->assertOk();

        $this->assertDatabaseHas('payment_intents', [
            'id' => $intent->id,
            'status' => PaymentIntentStatus::Succeeded->value,
        ]);
        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseHas('payment_webhook_events', [
            'provider_event_id' => 'PAYPAL_EVENT_APPROVED_ONCE',
            'status' => 'processed',
        ]);
    }

    public function test_capture_denied_webhook_marks_payment_intent_as_failed(): void
    {
        $this->configurePayPal();
        Http::preventStrayRequests();

        [, $booking] = $this->confirmedBookingScenario();
        $intent = PaymentIntent::factory()
            ->forBooking($booking)
            ->create([
                'provider' => PaymentProvider::PayPal,
                'provider_reference' => 'PAYPAL_ORDER_DENIED_WEBHOOK',
                'status' => PaymentIntentStatus::Processing,
            ]);
        $this->fakePayPalWebhookSignature();

        $this->postSignedPayPalWebhook([
            'id' => 'PAYPAL_EVENT_DENIED',
            'event_type' => 'PAYMENT.CAPTURE.DENIED',
            'resource_type' => 'capture',
            'resource' => [
                'id' => 'PAYPAL_CAPTURE_DENIED_WEBHOOK',
                'status' => 'DENIED',
                'amount' => [
                    'value' => '37.50',
                    'currency_code' => 'USD',
                ],
                'supplementary_data' => [
                    'related_ids' => [
                        'order_id' => 'PAYPAL_ORDER_DENIED_WEBHOOK',
                    ],
                ],
            ],
        ])->assertOk();

        $this->assertDatabaseHas('payment_intents', [
            'id' => $intent->id,
            'status' => PaymentIntentStatus::Failed->value,
        ]);
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_status_endpoint_returns_polling_guidance_and_capture_is_authorized(): void
    {
        [$client, $booking] = $this->confirmedBookingScenario();
        $intent = PaymentIntent::factory()
            ->forBooking($booking)
            ->processing()
            ->create([
                'provider' => PaymentProvider::PayPal,
                'provider_reference' => 'PAYPAL_ORDER_STATUS',
                'metadata' => ['paypal_order_status' => 'PENDING'],
            ]);
        $stranger = User::factory()->create();

        $this
            ->withHeaders($this->authHeaders($client))
            ->getJson("/api/v1/payment-intents/{$intent->id}/status")
            ->assertOk()
            ->assertJsonPath('status', 'processing')
            ->assertJsonPath('provider', 'paypal')
            ->assertJsonPath('provider_status', 'PENDING')
            ->assertJsonPath('booking_status', BookingStatus::Confirmed->value)
            ->assertJsonPath('is_final', false)
            ->assertJsonPath('next_poll_after_seconds', 5);

        $this
            ->withHeaders($this->authHeaders($stranger))
            ->postJson("/api/v1/payment-intents/{$intent->id}/capture")
            ->assertForbidden();
    }

    private function fakePayPalCapture(
        string $orderId,
        string $paymentIntentId,
        string $captureStatus,
    ): void {
        Http::fake($this->paypalCaptureResponses(
            orderId: $orderId,
            paymentIntentId: $paymentIntentId,
            captureStatus: $captureStatus,
        ));
    }

    private function paypalCaptureResponses(
        string $orderId,
        string $paymentIntentId,
        string $captureStatus,
    ): array {
        return [
            'api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response([
                'access_token' => 'fake-token',
                'expires_in' => 3600,
            ]),
            "api-m.sandbox.paypal.com/v2/checkout/orders/{$orderId}" => Http::response([
                'id' => $orderId,
                'status' => 'APPROVED',
                'purchase_units' => [[
                    'reference_id' => $paymentIntentId,
                    'custom_id' => $paymentIntentId,
                    'amount' => [
                        'value' => '37.50',
                        'currency_code' => 'USD',
                    ],
                ]],
            ]),
            "api-m.sandbox.paypal.com/v2/checkout/orders/{$orderId}/capture" => Http::response([
                'id' => $orderId,
                'status' => $captureStatus,
                'purchase_units' => [[
                    'reference_id' => $paymentIntentId,
                    'custom_id' => $paymentIntentId,
                    'payments' => [
                        'captures' => [[
                            'id' => 'PAYPAL_CAPTURE_'.$captureStatus,
                            'status' => $captureStatus,
                            'update_time' => now()->toAtomString(),
                            'amount' => [
                                'value' => '37.50',
                                'currency_code' => 'USD',
                            ],
                        ]],
                    ],
                ]],
            ]),
        ];

    }

    private function fakePayPalWebhookSignature(): void
    {
        Http::fake($this->paypalWebhookSignatureResponses());
    }

    private function paypalWebhookSignatureResponses(): array
    {
        return [
            'api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response([
                'access_token' => 'fake-token',
                'expires_in' => 3600,
            ]),
            'api-m.sandbox.paypal.com/v1/notifications/verify-webhook-signature' => Http::response([
                'verification_status' => 'SUCCESS',
            ]),
        ];
    }

    private function postSignedPayPalWebhook(array $payload)
    {
        return $this
            ->withHeaders([
                'PAYPAL-AUTH-ALGO' => 'SHA256withRSA',
                'PAYPAL-CERT-URL' => 'https://api-m.paypal.com/cert.pem',
                'PAYPAL-TRANSMISSION-ID' => 'transmission-signed',
                'PAYPAL-TRANSMISSION-SIG' => 'signature-signed',
                'PAYPAL-TRANSMISSION-TIME' => now()->toAtomString(),
            ])
            ->postJson('/api/v1/payments/webhooks/paypal', $payload);
    }

    private function configurePayPal(): void
    {
        config()->set('services.paypal.mode', 'sandbox');
        config()->set('services.paypal.client_id', 'test-client-id');
        config()->set('services.paypal.client_secret', 'test-client-secret');
        config()->set('services.paypal.webhook_id', 'test-webhook-id');
        config()->set('services.paypal.currency', 'USD');
        config()->set('services.paypal.exchange_rates', ['UYU' => 40]);
        config()->set(
            'services.paypal.success_url',
            'http://localhost:4200/payments/success'
        );
        config()->set(
            'services.paypal.cancel_url',
            'http://localhost:4200/payments/cancel'
        );
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
}
