<?php

namespace Tests\Feature\Payment;

use App\Enums\Booking\BookingStatus;
use App\Events\Payment\PaymentSucceeded;
use App\Models\Booking\Booking;
use App\Models\Payment\PaymentIntent;
use App\Models\Service\Service;
use App\Models\User\ProfessionalProfile;
use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class PaymentConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_repeated_success_requests_leave_only_one_payment(): void
    {
        Event::fake([PaymentSucceeded::class]);
        [$client, $booking, $intent] = $this->paymentIntentScenario();

        $this
            ->withHeaders($this->authHeaders($client))
            ->postJson("/api/v1/payment-intents/{$intent->id}/simulate-success")
            ->assertOk();

        $this
            ->withHeaders($this->authHeaders($client))
            ->postJson("/api/v1/payment-intents/{$intent->id}/simulate-success")
            ->assertOk();

        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseHas('payments', [
            'booking_id' => $booking->id,
        ]);
    }

    private function paymentIntentScenario(): array
    {
        $client = User::factory()->create();
        $professional = ProfessionalProfile::factory()->create();
        $service = Service::factory()->create([
            'professional_id' => $professional->id,
            'price' => 1500,
            'duration_minutes' => 60,
        ]);
        $booking = Booking::factory()->create([
            'service_id' => $service->id,
            'professional_id' => $professional->id,
            'client_id' => $client->id,
            'status' => BookingStatus::Confirmed,
            'confirmed_at' => now(),
            'price_snapshot' => $service->price,
            'duration_minutes_snapshot' => $service->duration_minutes,
            'modality' => $service->modality,
        ]);
        $intent = PaymentIntent::factory()
            ->forBooking($booking)
            ->pending()
            ->create();

        return [$client, $booking, $intent];
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
