<?php

namespace Tests\Feature\Booking;

use App\Models\Booking\Booking;
use App\Models\Service\Service;
use App\Models\User\ProfessionalProfile;
use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class BookingPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_owner_can_view_cancel_and_reschedule(): void
    {
        [$booking, $client] = $this->bookingScenario();

        $this->assertTrue(Gate::forUser($client)->allows('view', $booking));
        $this->assertTrue(Gate::forUser($client)->allows('cancel', $booking));
        $this->assertTrue(Gate::forUser($client)->allows('reschedule', $booking));
    }

    public function test_professional_owner_can_view_confirm_cancel_and_reschedule(): void
    {
        [$booking,, $professionalUser] = $this->bookingScenario();

        $this->assertTrue(Gate::forUser($professionalUser)->allows('view', $booking));
        $this->assertTrue(Gate::forUser($professionalUser)->allows('confirm', $booking));
        $this->assertTrue(Gate::forUser($professionalUser)->allows('cancel', $booking));
        $this->assertTrue(Gate::forUser($professionalUser)->allows('reschedule', $booking));
    }

    public function test_client_cannot_confirm(): void
    {
        [$booking, $client] = $this->bookingScenario();

        $this->assertFalse(Gate::forUser($client)->allows('confirm', $booking));
    }

    public function test_stranger_cannot_view_confirm_cancel_or_reschedule(): void
    {
        [$booking] = $this->bookingScenario();
        $stranger = User::factory()->create();

        $this->assertFalse(Gate::forUser($stranger)->allows('view', $booking));
        $this->assertFalse(Gate::forUser($stranger)->allows('confirm', $booking));
        $this->assertFalse(Gate::forUser($stranger)->allows('cancel', $booking));
        $this->assertFalse(Gate::forUser($stranger)->allows('reschedule', $booking));
    }

    private function bookingScenario(): array
    {
        $professionalUser = User::factory()->professional()->create();
        $profile = ProfessionalProfile::factory()->create([
            'user_id' => $professionalUser->id,
        ]);
        $client = User::factory()->create();
        $service = Service::factory()->create([
            'professional_id' => $profile->id,
        ]);
        $booking = Booking::factory()->create([
            'service_id' => $service->id,
            'professional_id' => $profile->id,
            'client_id' => $client->id,
        ]);

        return [$booking, $client, $professionalUser];
    }
}
