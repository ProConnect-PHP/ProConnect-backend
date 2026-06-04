<?php

namespace Tests\Feature\Payment;

use App\Enums\Booking\BookingStatus;
use App\Models\Booking\Booking;
use App\Models\Payment\Payment;
use App\Models\Payment\PaymentIntent;
use App\Models\Service\Service;
use App\Models\User\ProfessionalProfile;
use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class PaymentPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_owner_can_view_and_simulate_intent(): void
    {
        [$client, , $intent] = $this->paymentScenario();

        $this->assertTrue(Gate::forUser($client)->allows('view', $intent));
        $this->assertTrue(Gate::forUser($client)->allows('simulate', $intent));
    }

    public function test_other_client_cannot_view_or_simulate_intent(): void
    {
        [, , $intent] = $this->paymentScenario();
        $otherClient = User::factory()->create();

        $this->assertFalse(Gate::forUser($otherClient)->allows('view', $intent));
        $this->assertFalse(Gate::forUser($otherClient)->allows('simulate', $intent));
    }

    public function test_professional_owner_can_view_payment(): void
    {
        [, $professionalUser, , $payment] = $this->paymentScenario();

        $this->assertTrue(Gate::forUser($professionalUser)->allows('view', $payment));
    }

    public function test_other_professional_cannot_view_payment(): void
    {
        [, , , $payment] = $this->paymentScenario();
        [$otherProfessionalUser] = $this->professionalWithProfile();

        $this->assertFalse(Gate::forUser($otherProfessionalUser)->allows('view', $payment));
    }

    private function paymentScenario(): array
    {
        $client = User::factory()->create();
        [$professionalUser, $professional] = $this->professionalWithProfile();
        $service = Service::factory()->create([
            'professional_id' => $professional->id,
            'price' => 1500,
            'duration_minutes' => 60,
        ]);
        $booking = Booking::factory()->create([
            'service_id' => $service->id,
            'professional_id' => $professional->id,
            'client_id' => $client->id,
            'status' => BookingStatus::Paid,
            'confirmed_at' => now()->subDay(),
            'paid_at' => now(),
            'price_snapshot' => $service->price,
            'duration_minutes_snapshot' => $service->duration_minutes,
            'modality' => $service->modality,
        ]);
        $intent = PaymentIntent::factory()
            ->forBooking($booking)
            ->succeeded()
            ->create();
        $payment = Payment::factory()
            ->forPaymentIntent($intent)
            ->succeeded()
            ->create();

        return [$client, $professionalUser, $intent, $payment];
    }

    private function professionalWithProfile(): array
    {
        $user = User::factory()->professional()->create();
        $profile = ProfessionalProfile::factory()->create([
            'user_id' => $user->id,
        ]);

        return [$user, $profile];
    }
}
