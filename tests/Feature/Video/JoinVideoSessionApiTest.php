<?php

namespace Tests\Feature\Video;

use App\Actions\Video\EnsureVideoSessionForBookingAction;
use App\Enums\Booking\BookingStatus;
use App\Enums\Video\VideoSessionStatus;
use App\Models\Booking\Booking;
use App\Models\Service\Service;
use App\Models\User\ProfessionalProfile;
use App\Models\User\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JoinVideoSessionApiTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('proconnect.video.join_before_minutes', 15);
        config()->set('proconnect.video.join_after_minutes', 120);
    }

    public function test_client_can_join_inside_window(): void
    {
        [$videoSession, $client] = $this->videoSessionScenario();
        Carbon::setTestNow('2026-06-15 08:50:00');

        $response = $this
            ->withHeaders($this->authHeaders($client))
            ->postJson("/api/v1/video-sessions/{$videoSession->id}/join");

        $response
            ->assertOk()
            ->assertJsonPath('join.video_session_id', $videoSession->id)
            ->assertJsonPath('join.join_url', $videoSession->join_url)
            ->assertJsonPath('join.participant.role', 'client')
            ->assertJson(fn ($json) => $json->whereType('join.access_token', 'string')->etc());

        $this->assertDatabaseHas('video_session_participants', [
            'video_session_id' => $videoSession->id,
            'user_id' => $client->id,
            'role' => 'client',
            'join_count' => 1,
        ]);
        $this->assertDatabaseHas('video_sessions', [
            'id' => $videoSession->id,
            'status' => VideoSessionStatus::InProgress->value,
        ]);
    }

    public function test_professional_can_join_inside_window(): void
    {
        [$videoSession, , $professionalUser] = $this->videoSessionScenario();
        Carbon::setTestNow('2026-06-15 08:50:00');

        $this
            ->withHeaders($this->authHeaders($professionalUser))
            ->postJson("/api/v1/video-sessions/{$videoSession->id}/join")
            ->assertOk()
            ->assertJsonPath('join.participant.role', 'professional');
    }

    public function test_other_user_cannot_join(): void
    {
        [$videoSession] = $this->videoSessionScenario();
        $otherUser = User::factory()->create();
        Carbon::setTestNow('2026-06-15 08:50:00');

        $this
            ->withHeaders($this->authHeaders($otherUser))
            ->postJson("/api/v1/video-sessions/{$videoSession->id}/join")
            ->assertForbidden()
            ->assertJsonPath('error.type', 'Forbidden');
    }

    public function test_cannot_join_before_window(): void
    {
        [$videoSession, $client] = $this->videoSessionScenario();
        Carbon::setTestNow('2026-06-15 08:44:00');

        $this
            ->withHeaders($this->authHeaders($client))
            ->postJson("/api/v1/video-sessions/{$videoSession->id}/join")
            ->assertConflict()
            ->assertJsonPath('error.type', 'VideoSessionJoinWindowClosed');
    }

    public function test_cannot_join_after_window(): void
    {
        [$videoSession, $client] = $this->videoSessionScenario();
        Carbon::setTestNow('2026-06-15 12:01:00');

        $this
            ->withHeaders($this->authHeaders($client))
            ->postJson("/api/v1/video-sessions/{$videoSession->id}/join")
            ->assertConflict()
            ->assertJsonPath('error.type', 'VideoSessionJoinWindowClosed');
    }

    public function test_cannot_join_cancelled_session(): void
    {
        [$videoSession, $client] = $this->videoSessionScenario();
        $videoSession->update([
            'status' => VideoSessionStatus::Cancelled,
            'cancelled_at' => now(),
        ]);
        Carbon::setTestNow('2026-06-15 08:50:00');

        $this
            ->withHeaders($this->authHeaders($client))
            ->postJson("/api/v1/video-sessions/{$videoSession->id}/join")
            ->assertConflict()
            ->assertJsonPath('error.type', 'VideoSessionCancelled');
    }

    public function test_cannot_join_ended_session(): void
    {
        [$videoSession, $client] = $this->videoSessionScenario();
        $videoSession->update([
            'status' => VideoSessionStatus::Ended,
            'ended_at' => now(),
        ]);
        Carbon::setTestNow('2026-06-15 08:50:00');

        $this
            ->withHeaders($this->authHeaders($client))
            ->postJson("/api/v1/video-sessions/{$videoSession->id}/join")
            ->assertConflict()
            ->assertJsonPath('error.type', 'VideoSessionEnded');
    }

    public function test_join_increments_existing_participant_count(): void
    {
        [$videoSession, $client] = $this->videoSessionScenario();
        Carbon::setTestNow('2026-06-15 08:50:00');

        $this
            ->withHeaders($this->authHeaders($client))
            ->postJson("/api/v1/video-sessions/{$videoSession->id}/join")
            ->assertOk();

        $this
            ->withHeaders($this->authHeaders($client))
            ->postJson("/api/v1/video-sessions/{$videoSession->id}/join")
            ->assertOk()
            ->assertJsonPath('join.participant.join_count', 2);

        $this->assertDatabaseHas('video_session_participants', [
            'video_session_id' => $videoSession->id,
            'user_id' => $client->id,
            'join_count' => 2,
        ]);
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
            'starts_at' => '2026-06-15 09:00:00',
            'ends_at' => '2026-06-15 10:00:00',
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
