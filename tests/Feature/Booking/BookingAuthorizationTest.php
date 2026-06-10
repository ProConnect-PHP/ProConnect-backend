<?php

namespace Tests\Feature\Booking;

use App\Models\Booking\Booking;
use App\Models\Service\Service;
use App\Models\User\ProfessionalProfile;
use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_cannot_view_another_clients_booking(): void
    {
        [$booking] = $this->bookingScenario();
        $otherClient = User::factory()->create();

        $this->withHeaders($this->authHeaders($otherClient))
            ->getJson("/api/v1/bookings/{$booking->id}")
            ->assertForbidden()
            ->assertJsonPath('error.type', 'Forbidden');
    }

    public function test_professional_cannot_view_another_professionals_booking(): void
    {
        [$booking] = $this->bookingScenario();
        $otherProfessional = User::factory()->professional()->create();
        ProfessionalProfile::factory()->create([
            'user_id' => $otherProfessional->id,
        ]);

        $this->withHeaders($this->authHeaders($otherProfessional))
            ->getJson("/api/v1/bookings/{$booking->id}")
            ->assertForbidden()
            ->assertJsonPath('error.type', 'Forbidden');
    }

    public function test_professional_cannot_confirm_another_professionals_booking(): void
    {
        [$booking] = $this->bookingScenario();
        $otherProfessional = User::factory()->professional()->create();
        ProfessionalProfile::factory()->create([
            'user_id' => $otherProfessional->id,
        ]);

        $this->withHeaders($this->authHeaders($otherProfessional))
            ->postJson("/api/v1/bookings/{$booking->id}/confirm")
            ->assertForbidden()
            ->assertJsonPath('error.type', 'Forbidden');
    }

    private function bookingScenario(): array
    {
        $professionalUser = User::factory()->professional()->create();
        $professional = ProfessionalProfile::factory()->create([
            'user_id' => $professionalUser->id,
        ]);
        $client = User::factory()->create();
        $service = Service::factory()->create([
            'professional_id' => $professional->id,
        ]);
        $booking = Booking::factory()->create([
            'service_id' => $service->id,
            'professional_id' => $professional->id,
            'client_id' => $client->id,
        ]);

        return [$booking, $client, $professionalUser];
    }

    private function authHeaders(User $user): array
    {
        return [
            'Authorization' => 'Bearer '.auth('user_jwt')->login($user),
            'Accept' => 'application/json',
        ];
    }
}
