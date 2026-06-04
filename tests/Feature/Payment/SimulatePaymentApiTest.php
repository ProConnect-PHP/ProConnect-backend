<?php

namespace Tests\Feature\Payment;

use App\Enums\Booking\BookingStatus;
use App\Enums\Payment\PaymentIntentStatus;
use App\Enums\Payment\PaymentStatus;
use App\Events\Payment\PaymentFailed;
use App\Events\Payment\PaymentSucceeded;
use App\Models\Booking\Booking;
use App\Models\Payment\PaymentIntent;
use App\Models\Service\Service;
use App\Models\User\ProfessionalProfile;
use App\Models\User\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class SimulatePaymentApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-06-01 12:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_client_can_simulate_successful_payment(): void
    {
        Event::fake([PaymentSucceeded::class]);
        [$client, $booking, $intent] = $this->paymentIntentScenario();

        $response = $this
            ->withHeaders($this->authHeaders($client))
            ->postJson("/api/v1/payment-intents/{$intent->id}/simulate-success");

        $response
            ->assertOk()
            ->assertJsonPath('payment.booking_id', $booking->id)
            ->assertJsonPath('payment.status', PaymentStatus::Succeeded->value)
            ->assertJsonPath('payment.amount', 1500)
            ->assertJsonPath('payment.currency', 'UYU');

        $this->assertDatabaseHas('payment_intents', [
            'id' => $intent->id,
            'status' => PaymentIntentStatus::Succeeded->value,
        ]);
        $this->assertDatabaseHas('payments', [
            'payment_intent_id' => $intent->id,
            'booking_id' => $booking->id,
            'status' => PaymentStatus::Succeeded->value,
        ]);
        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'status' => BookingStatus::Paid->value,
        ]);
        $this->assertNotNull($booking->refresh()->paid_at);
        Event::assertDispatched(PaymentSucceeded::class);
    }

    public function test_guest_cannot_simulate_payment(): void
    {
        [, , $intent] = $this->paymentIntentScenario();

        $this
            ->postJson("/api/v1/payment-intents/{$intent->id}/simulate-success")
            ->assertUnauthorized()
            ->assertJsonPath('error.type', 'Unauthorized');
    }

    public function test_other_client_cannot_simulate_success(): void
    {
        [, , $intent] = $this->paymentIntentScenario();
        $otherClient = User::factory()->create();

        $this
            ->withHeaders($this->authHeaders($otherClient))
            ->postJson("/api/v1/payment-intents/{$intent->id}/simulate-success")
            ->assertForbidden()
            ->assertJsonPath('error.type', 'Forbidden');
    }

    public function test_success_is_idempotent_for_same_intent(): void
    {
        Event::fake([PaymentSucceeded::class]);
        [$client, $booking, $intent] = $this->paymentIntentScenario();

        $first = $this
            ->withHeaders($this->authHeaders($client))
            ->postJson("/api/v1/payment-intents/{$intent->id}/simulate-success");

        $second = $this
            ->withHeaders($this->authHeaders($client))
            ->postJson("/api/v1/payment-intents/{$intent->id}/simulate-success");

        $first->assertOk();
        $second
            ->assertOk()
            ->assertJsonPath('payment.id', $first->json('payment.id'));

        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'status' => BookingStatus::Paid->value,
        ]);
    }

    public function test_failed_intent_cannot_be_simulated_as_success(): void
    {
        [$client, , $intent] = $this->paymentIntentScenario();
        $intent->update([
            'status' => PaymentIntentStatus::Failed,
            'failed_at' => now(),
        ]);

        $this
            ->withHeaders($this->authHeaders($client))
            ->postJson("/api/v1/payment-intents/{$intent->id}/simulate-success")
            ->assertConflict()
            ->assertJsonPath('error.type', 'PaymentIntentNotProcessable');
    }

    public function test_expired_intent_cannot_be_simulated_as_success(): void
    {
        [$client, , $intent] = $this->paymentIntentScenario();
        $intent->update([
            'expires_at' => now()->subMinute(),
        ]);

        $this
            ->withHeaders($this->authHeaders($client))
            ->postJson("/api/v1/payment-intents/{$intent->id}/simulate-success")
            ->assertConflict()
            ->assertJsonPath('error.type', 'PaymentIntentExpired');

        $this->assertDatabaseHas('payment_intents', [
            'id' => $intent->id,
            'status' => PaymentIntentStatus::Expired->value,
        ]);
    }

    public function test_failure_leaves_booking_confirmed_without_payment(): void
    {
        Event::fake([PaymentFailed::class]);
        [$client, $booking, $intent] = $this->paymentIntentScenario();

        $response = $this
            ->withHeaders($this->authHeaders($client))
            ->postJson("/api/v1/payment-intents/{$intent->id}/simulate-failure", [
                'failure_reason' => 'Tarjeta simulada rechazada.',
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('payment_intent.status', PaymentIntentStatus::Failed->value)
            ->assertJsonPath('payment_intent.failure_reason', 'Tarjeta simulada rechazada.');

        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'status' => BookingStatus::Confirmed->value,
        ]);
        $this->assertDatabaseCount('payments', 0);
        Event::assertDispatched(PaymentFailed::class);
    }

    public function test_new_intent_can_be_created_after_failure(): void
    {
        [$client, $booking, $intent] = $this->paymentIntentScenario();

        $this
            ->withHeaders($this->authHeaders($client))
            ->postJson("/api/v1/payment-intents/{$intent->id}/simulate-failure")
            ->assertOk();

        $response = $this
            ->withHeaders($this->authHeaders($client))
            ->postJson("/api/v1/bookings/{$booking->id}/payment-intents");

        $response
            ->assertCreated()
            ->assertJsonPath('payment_intent.status', PaymentIntentStatus::Pending->value);

        $this->assertDatabaseCount('payment_intents', 2);
    }

    private function paymentIntentScenario(): array
    {
        [$client, $booking] = $this->confirmedBookingScenario();
        $intent = PaymentIntent::factory()
            ->forBooking($booking)
            ->pending()
            ->create([
                'amount' => 1500,
                'currency' => 'UYU',
            ]);

        return [$client, $booking, $intent];
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
            'starts_at' => '2026-06-15 09:00:00',
            'ends_at' => '2026-06-15 10:00:00',
            'status' => BookingStatus::Confirmed,
            'confirmed_at' => now(),
            'modality' => $service->modality,
            'price_snapshot' => $service->price,
            'duration_minutes_snapshot' => $service->duration_minutes,
        ]);

        return [$client, $booking, $professional, $service];
    }

    private function authHeaders(User $user): array
    {
        $token = auth('user_jwt')->login($user);

        return [
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ];
    }
}
