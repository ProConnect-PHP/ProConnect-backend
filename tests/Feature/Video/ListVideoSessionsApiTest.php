<?php

namespace Tests\Feature\Video;

use App\Actions\Video\EnsureVideoSessionForBookingAction;
use App\Enums\Booking\BookingStatus;
use App\Models\Booking\Booking;
use App\Models\Service\Service;
use App\Models\User\ProfessionalProfile;
use App\Models\User\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ListVideoSessionsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_my_video_sessions_returns_only_authenticated_clients_sessions(): void
    {
        [$videoSession, $client] = $this->videoSessionScenario();
        [$otherVideoSession] = $this->videoSessionScenario();

        $response = $this
            ->withHeaders($this->authHeaders($client))
            ->getJson('/api/v1/video-sessions/my');

        $response
            ->assertOk()
            ->assertJsonCount(1, 'video_sessions')
            ->assertJsonPath('video_sessions.0.id', $videoSession->id);

        $this->assertNotSame($otherVideoSession->id, $response->json('video_sessions.0.id'));
    }

    public function test_professional_video_sessions_returns_only_professionals_sessions(): void
    {
        [$videoSession, , $professionalUser] = $this->videoSessionScenario();
        [$otherVideoSession] = $this->videoSessionScenario();

        $response = $this
            ->withHeaders($this->authHeaders($professionalUser))
            ->getJson('/api/v1/professional/video-sessions');

        $response
            ->assertOk()
            ->assertJsonCount(1, 'video_sessions')
            ->assertJsonPath('video_sessions.0.id', $videoSession->id);

        $this->assertNotSame($otherVideoSession->id, $response->json('video_sessions.0.id'));
    }

    public function test_user_without_professional_profile_cannot_list_professional_video_sessions(): void
    {
        $client = User::factory()->create();

        $this
            ->withHeaders($this->authHeaders($client))
            ->getJson('/api/v1/professional/video-sessions')
            ->assertForbidden()
            ->assertJsonPath('error.type', 'Forbidden');
    }

    public function test_video_session_list_pagination_works(): void
    {
        [$firstVideoSession, $client] = $this->videoSessionScenario('2026-06-15 09:00:00');
        [$secondVideoSession] = $this->videoSessionScenario('2026-06-16 09:00:00', $client);

        $response = $this
            ->withHeaders($this->authHeaders($client))
            ->getJson('/api/v1/video-sessions/my?per_page=1');

        $response
            ->assertOk()
            ->assertJsonCount(1, 'video_sessions')
            ->assertJsonPath('video_sessions.0.id', $secondVideoSession->id)
            ->assertJsonPath('meta.per_page', 1)
            ->assertJsonPath('meta.total', 2);

        $this->assertNotSame($firstVideoSession->id, $response->json('video_sessions.0.id'));
    }

    private function videoSessionScenario(
        string $startsAt = '2026-06-15 09:00:00',
        ?User $client = null
    ): array {
        $professionalUser = User::factory()->professional()->create();
        $professionalProfile = ProfessionalProfile::factory()->create([
            'user_id' => $professionalUser->id,
        ]);
        $client ??= User::factory()->create();
        $service = Service::factory()->create([
            'professional_id' => $professionalProfile->id,
            'modality' => 'remota',
            'duration_minutes' => 60,
        ]);
        $booking = Booking::factory()->create([
            'service_id' => $service->id,
            'professional_id' => $professionalProfile->id,
            'client_id' => $client->id,
            'starts_at' => $startsAt,
            'ends_at' => Carbon::parse($startsAt)->addHour(),
            'status' => BookingStatus::Confirmed,
            'confirmed_at' => now(),
            'modality' => 'remota',
            'price_snapshot' => $service->price,
            'duration_minutes_snapshot' => 60,
        ]);

        return [app(EnsureVideoSessionForBookingAction::class)($booking), $client, $professionalUser, $booking];
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
