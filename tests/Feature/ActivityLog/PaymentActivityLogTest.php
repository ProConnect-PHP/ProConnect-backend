<?php

namespace Tests\Feature\ActivityLog;

use App\Enums\Booking\BookingStatus;
use App\Enums\Payment\PaymentIntentStatus;
use App\Enums\Payment\PaymentProvider;
use App\Models\Booking\Booking;
use App\Models\Package\PackageProduct;
use App\Models\Payment\PaymentIntent;
use App\Models\Service\Service;
use App\Models\User\ProfessionalProfile;
use App\Models\User\User;
use App\Support\ActivityLog\ActivityLogEvent;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PaymentActivityLogTest extends TestCase
{
    use DatabaseMigrations;
    use InteractsWithActivityLogs;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-06-01 12:00:00');
        $this->useSynchronousActivityLogQueue();
        $this->clearActivityLogs();
    }

    protected function tearDown(): void
    {
        $this->clearActivityLogs();
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_payment_creation_creates_activity_log(): void
    {
        [$client, $booking] = $this->confirmedBookingScenario();

        $response = $this
            ->withHeaders($this->authHeaders($client))
            ->postJson("/api/v1/bookings/{$booking->id}/payment-intents", [
                'metadata' => ['source' => 'activity-test'],
            ])
            ->assertCreated();

        $log = $this->activityLog(ActivityLogEvent::PaymentCreated->value);

        $this->assertNotNull($log);
        $this->assertSame($response->json('payment_intent.id'), $log->entity_id);
        $this->assertSame($booking->id, $log->metadata['booking_id']);
        $this->assertArrayNotHasKey('metadata', $log->metadata);
    }

    public function test_package_purchase_creates_activity_log(): void
    {
        $client = User::factory()->create();
        $packageProduct = PackageProduct::factory()->active()->create([
            'sessions_count' => 4,
            'price' => 5600,
        ]);

        $response = $this
            ->withHeaders($this->authHeaders($client))
            ->postJson("/api/v1/package-products/{$packageProduct->id}/purchase")
            ->assertOk();

        $log = $this->activityLog(ActivityLogEvent::PackagePurchased->value);

        $this->assertNotNull($log);
        $this->assertSame($response->json('client_package.id'), $log->entity_id);
        $this->assertSame($packageProduct->id, $log->metadata['package_product_id']);
        $this->assertSame(4, $log->metadata['sessions_total']);
        $this->assertSame(4, $log->metadata['sessions_remaining']);
    }

    public function test_checkout_and_simulated_approval_keep_client_actor_mode(): void
    {
        [$client, $booking] = $this->confirmedBookingScenario();
        $intentId = $this
            ->withHeaders($this->authHeaders($client))
            ->postJson("/api/v1/bookings/{$booking->id}/payment-intents")
            ->assertCreated()
            ->json('payment_intent.id');

        $this
            ->withHeaders($this->authHeaders($client))
            ->postJson("/api/v1/payment-intents/{$intentId}/checkout", [
                'provider' => 'simulator',
            ])
            ->assertOk();
        $this
            ->withHeaders($this->authHeaders($client))
            ->postJson("/api/v1/payment-intents/{$intentId}/simulate-success")
            ->assertOk();

        $this->assertSame(
            'client',
            $this->activityLog(
                ActivityLogEvent::PaymentCheckoutCreated->value
            )?->acting_as
        );
        $this->assertSame(
            'client',
            $this->activityLog(ActivityLogEvent::PaymentApproved->value)?->acting_as
        );
        $this->assertSame(
            'client',
            $this->activityLog(ActivityLogEvent::BookingPaid->value)?->acting_as
        );
    }

    public function test_webhook_approval_is_logged_as_system(): void
    {
        Http::preventStrayRequests();
        config()->set('services.mercadopago.access_token', 'test-token');
        config()->set('services.mercadopago.webhook_secret', null);
        config()->set('services.mercadopago.webhook_secret_test', null);
        config()->set(
            'services.mercadopago.webhook_secret_production',
            null
        );
        config()->set(
            'services.mercadopago.webhook_signature_required',
            false
        );
        [, $booking] = $this->confirmedBookingScenario();
        $intent = PaymentIntent::factory()
            ->forBooking($booking)
            ->create([
                'provider' => PaymentProvider::MercadoPago,
                'provider_reference' => 'mp_pref_log',
                'status' => PaymentIntentStatus::CheckoutCreated,
            ]);
        Http::fake([
            'api.mercadopago.com/v1/payments/163086264411' => Http::response([
                'id' => '163086264411',
                'status' => 'approved',
                'external_reference' => $intent->id,
                'date_approved' => now()->toAtomString(),
                'transaction_amount' => 1800,
                'currency_id' => 'UYU',
            ]),
        ]);

        $this->postJson('/api/v1/payments/webhooks/mercadopago', [
            'id' => 'mp_event_log',
            'type' => 'payment',
            'action' => 'payment.updated',
            'data' => ['id' => '163086264411'],
        ])->assertOk();

        $this->assertSame(
            'system',
            $this->activityLog(ActivityLogEvent::PaymentApproved->value)?->acting_as
        );
        $this->assertSame(
            'system',
            $this->activityLog(ActivityLogEvent::BookingPaid->value)?->acting_as
        );
        $this->assertSame(
            'system',
            $this->activityLog(
                ActivityLogEvent::PaymentWebhookProcessed->value
            )?->acting_as
        );
    }

    private function confirmedBookingScenario(): array
    {
        $client = User::factory()->create();
        $professional = ProfessionalProfile::factory()->create();
        $service = Service::factory()->create([
            'professional_id' => $professional->id,
            'price' => 1800,
            'duration_minutes' => 60,
            'modality' => 'remota',
        ]);
        $booking = Booking::factory()->create([
            'service_id' => $service->id,
            'professional_id' => $professional->id,
            'client_id' => $client->id,
            'starts_at' => '2026-06-15 09:00:00',
            'ends_at' => '2026-06-15 10:00:00',
            'status' => BookingStatus::Confirmed,
            'confirmed_at' => now(),
            'modality' => $service->modality,
            'price_snapshot' => $service->price,
            'duration_minutes_snapshot' => $service->duration_minutes,
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
