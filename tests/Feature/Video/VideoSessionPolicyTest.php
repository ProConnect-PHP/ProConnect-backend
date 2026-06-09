<?php

namespace Tests\Feature\Video;

use App\Actions\Video\EnsureVideoSessionForBookingAction;
use App\Enums\Booking\BookingStatus;
use App\Models\Booking\Booking;
use App\Models\Service\Service;
use App\Models\User\ProfessionalProfile;
use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class VideoSessionPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_and_professional_can_view_and_join_video_session(): void
    {
        [$videoSession, $client, $professionalUser] = $this->videoSessionScenario();

        $this->assertTrue(Gate::forUser($client)->allows('view', $videoSession));
        $this->assertTrue(Gate::forUser($client)->allows('join', $videoSession));
        $this->assertTrue(Gate::forUser($professionalUser)->allows('view', $videoSession));
        $this->assertTrue(Gate::forUser($professionalUser)->allows('join', $videoSession));
    }

    public function test_only_professional_can_end_video_session(): void
    {
        [$videoSession, $client, $professionalUser] = $this->videoSessionScenario();

        $this->assertFalse(Gate::forUser($client)->allows('end', $videoSession));
        $this->assertTrue(Gate::forUser($professionalUser)->allows('end', $videoSession));
    }

    public function test_stranger_cannot_view_join_or_end_video_session(): void
    {
        [$videoSession] = $this->videoSessionScenario();
        $stranger = User::factory()->create();

        $this->assertFalse(Gate::forUser($stranger)->allows('view', $videoSession));
        $this->assertFalse(Gate::forUser($stranger)->allows('join', $videoSession));
        $this->assertFalse(Gate::forUser($stranger)->allows('end', $videoSession));
    }

    private function videoSessionScenario(): array
    {
        $professionalUser = User::factory()->professional()->create();
        $professionalProfile = ProfessionalProfile::factory()->create([
            'user_id' => $professionalUser->id,
        ]);
        $client = User::factory()->create();
        $service = Service::factory()->create([
            'professional_id' => $professionalProfile->id,
            'modality' => 'remota',
            'duration_minutes' => 60,
        ]);
        $booking = Booking::factory()->create([
            'service_id' => $service->id,
            'professional_id' => $professionalProfile->id,
            'client_id' => $client->id,
            'status' => BookingStatus::Confirmed,
            'confirmed_at' => now(),
            'modality' => 'remota',
            'price_snapshot' => $service->price,
            'duration_minutes_snapshot' => 60,
        ]);

        return [app(EnsureVideoSessionForBookingAction::class)($booking), $client, $professionalUser, $booking];
    }
}
