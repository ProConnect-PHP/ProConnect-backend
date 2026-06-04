<?php

namespace Tests\Feature\Payment;

use App\Enums\Booking\BookingStatus;
use App\Enums\Payment\PaymentIntentStatus;
use App\Models\Booking\Booking;
use App\Models\Payment\PaymentIntent;
use App\Models\Service\Service;
use App\Models\User\ProfessionalProfile;
use App\Models\User\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreatePaymentIntentApiTest extends TestCase
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

    public function test_client_can_create_intent_for_own_confirmed_booking(): void
    {
        [$client, $booking] = $this->confirmedBookingScenario([
            'price_snapshot' => 1800,
        ]);

        $response = $this
            ->withHeaders($this->authHeaders($client))
            ->postJson("/api/v1/bookings/{$booking->id}/payment-intents", [
                'metadata' => ['source' => 'feature-test'],
            ]);

        $response
            ->assertCreated()
            ->assertJsonPath('payment_intent.booking_id', $booking->id)
            ->assertJsonPath('payment_intent.status', PaymentIntentStatus::Pending->value)
            ->assertJsonPath('payment_intent.amount', 1800)
            ->assertJsonPath('payment_intent.currency', 'UYU');

        $this->assertDatabaseHas('payment_intents', [
            'booking_id' => $booking->id,
            'client_id' => $client->id,
            'status' => PaymentIntentStatus::Pending->value,
            'amount' => 1800,
            'currency' => 'UYU',
        ]);
    }

    public function test_guest_cannot_create_payment_intent(): void
    {
        [, $booking] = $this->confirmedBookingScenario();

        $this
            ->postJson("/api/v1/bookings/{$booking->id}/payment-intents")
            ->assertUnauthorized()
            ->assertJsonPath('error.type', 'Unauthorized');
    }

    public function test_client_cannot_pay_another_clients_booking(): void
    {
        [, $booking] = $this->confirmedBookingScenario();
        $otherClient = User::factory()->create();

        $this
            ->withHeaders($this->authHeaders($otherClient))
            ->postJson("/api/v1/bookings/{$booking->id}/payment-intents")
            ->assertForbidden()
            ->assertJsonPath('error.type', 'Forbidden');
    }

    public function test_only_confirmed_bookings_are_payable(): void
    {
        foreach ([
            BookingStatus::Pending,
            BookingStatus::Cancelled,
            BookingStatus::Completed,
            BookingStatus::NoShow,
        ] as $status) {
            [$client, $booking] = $this->bookingScenario([
                'status' => $status,
                'cancelled_at' => $status === BookingStatus::Cancelled ? now() : null,
                'completed_at' => $status === BookingStatus::Completed ? now() : null,
                'no_show_at' => $status === BookingStatus::NoShow ? now() : null,
            ]);

            $this
                ->withHeaders($this->authHeaders($client))
                ->postJson("/api/v1/bookings/{$booking->id}/payment-intents")
                ->assertConflict()
                ->assertJsonPath('error.type', 'BookingNotPayable');
        }
    }

    public function test_paid_booking_cannot_create_new_intent(): void
    {
        [$client, $booking] = $this->bookingScenario([
            'status' => BookingStatus::Paid,
            'confirmed_at' => now()->subDay(),
            'paid_at' => now(),
        ]);

        $this
            ->withHeaders($this->authHeaders($client))
            ->postJson("/api/v1/bookings/{$booking->id}/payment-intents")
            ->assertConflict()
            ->assertJsonPath('error.type', 'BookingAlreadyPaid');
    }

    public function test_existing_pending_intent_is_reused(): void
    {
        [$client, $booking] = $this->confirmedBookingScenario();
        $intent = PaymentIntent::factory()
            ->forBooking($booking)
            ->pending()
            ->create();

        $response = $this
            ->withHeaders($this->authHeaders($client))
            ->postJson("/api/v1/bookings/{$booking->id}/payment-intents");

        $response
            ->assertCreated()
            ->assertJsonPath('payment_intent.id', $intent->id);

        $this->assertDatabaseCount('payment_intents', 1);
    }

    public function test_failed_intent_allows_a_new_intent(): void
    {
        [$client, $booking] = $this->confirmedBookingScenario();
        $failedIntent = PaymentIntent::factory()
            ->forBooking($booking)
            ->failed()
            ->create();

        $response = $this
            ->withHeaders($this->authHeaders($client))
            ->postJson("/api/v1/bookings/{$booking->id}/payment-intents");

        $response
            ->assertCreated()
            ->assertJsonPath('payment_intent.status', PaymentIntentStatus::Pending->value);

        $this->assertNotSame($failedIntent->id, $response->json('payment_intent.id'));
        $this->assertDatabaseCount('payment_intents', 2);
    }

    private function confirmedBookingScenario(array $overrides = []): array
    {
        return $this->bookingScenario([
            'status' => BookingStatus::Confirmed,
            'confirmed_at' => now(),
            ...$overrides,
        ]);
    }

    private function bookingScenario(array $overrides = []): array
    {
        $client = User::factory()->create();
        $professional = ProfessionalProfile::factory()->create();
        $service = Service::factory()->create([
            'professional_id' => $professional->id,
            'price' => $overrides['price_snapshot'] ?? 1500,
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
            ...$overrides,
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
