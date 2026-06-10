<?php

namespace Tests\Feature\Video;

use App\Actions\Video\EnsureVideoSessionForBookingAction;
use App\Enums\Booking\BookingStatus;
use App\Models\Booking\Booking;
use App\Models\Service\Service;
use App\Models\User\ProfessionalProfile;
use App\Models\User\User;
use App\Models\Video\VideoSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EnsureVideoSessionApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('proconnect.video.provider', 'simulator');
    }

    public function test_client_owner_can_view_video_session_for_their_booking(): void
    {
        [$booking, $client] = $this->bookingScenario();
        $videoSession = app(EnsureVideoSessionForBookingAction::class)($booking);

        $this
            ->withHeaders($this->authHeaders($client))
            ->getJson("/api/v1/bookings/{$booking->id}/video-session")
            ->assertOk()
            ->assertJsonPath('video_session.id', $videoSession->id);
    }

    public function test_professional_owner_can_view_video_session_for_their_booking(): void
    {
        [$booking, , $professionalUser] = $this->bookingScenario();
        $videoSession = app(EnsureVideoSessionForBookingAction::class)($booking);

        $this
            ->withHeaders($this->authHeaders($professionalUser))
            ->getJson("/api/v1/bookings/{$booking->id}/video-session")
            ->assertOk()
            ->assertJsonPath('video_session.id', $videoSession->id);
    }

    public function test_other_client_cannot_view_video_session(): void
    {
        [$booking] = $this->bookingScenario();
        app(EnsureVideoSessionForBookingAction::class)($booking);
        $otherClient = User::factory()->create();

        $this
            ->withHeaders($this->authHeaders($otherClient))
            ->getJson("/api/v1/bookings/{$booking->id}/video-session")
            ->assertForbidden()
            ->assertJsonPath('error.type', 'Forbidden');
    }

    public function test_can_create_video_session_for_remote_confirmed_booking(): void
    {
        [$booking, $client] = $this->bookingScenario(['modality' => 'remota']);

        $this
            ->withHeaders($this->authHeaders($client))
            ->postJson("/api/v1/bookings/{$booking->id}/video-session")
            ->assertCreated()
            ->assertJsonPath('video_session.booking_id', $booking->id)
            ->assertJsonPath('video_session.provider', 'simulator')
            ->assertJsonPath('video_session.room_name', 'booking-'.$booking->id);

        $this->assertDatabaseHas('video_sessions', [
            'booking_id' => $booking->id,
            'status' => 'scheduled',
        ]);
    }

    public function test_can_create_video_session_for_hybrid_confirmed_booking(): void
    {
        [$booking, $client] = $this->bookingScenario(['modality' => 'hibrida']);

        $this
            ->withHeaders($this->authHeaders($client))
            ->postJson("/api/v1/bookings/{$booking->id}/video-session")
            ->assertCreated()
            ->assertJsonPath('video_session.booking_id', $booking->id);
    }

    public function test_cannot_create_video_session_for_in_person_booking(): void
    {
        [$booking, $client] = $this->bookingScenario(['modality' => 'presencial']);

        $this
            ->withHeaders($this->authHeaders($client))
            ->postJson("/api/v1/bookings/{$booking->id}/video-session")
            ->assertConflict()
            ->assertJsonPath('error.type', 'VideoSessionNotAllowedForModality');

        $this->assertDatabaseMissing('video_sessions', [
            'booking_id' => $booking->id,
        ]);
    }

    public function test_creating_twice_returns_the_same_video_session(): void
    {
        [$booking, $client] = $this->bookingScenario();

        $first = $this
            ->withHeaders($this->authHeaders($client))
            ->postJson("/api/v1/bookings/{$booking->id}/video-session");

        $second = $this
            ->withHeaders($this->authHeaders($client))
            ->postJson("/api/v1/bookings/{$booking->id}/video-session");

        $first->assertCreated();
        $second
            ->assertCreated()
            ->assertJsonPath('video_session.id', $first->json('video_session.id'));

        $this->assertSame(
            1,
            VideoSession::query()->where('booking_id', $booking->id)->count()
        );
    }

    public function test_cannot_create_video_session_for_cancelled_booking_without_existing_room(): void
    {
        [$booking, $client] = $this->bookingScenario([
            'status' => BookingStatus::Cancelled,
            'cancelled_at' => now(),
        ]);

        $this
            ->withHeaders($this->authHeaders($client))
            ->postJson("/api/v1/bookings/{$booking->id}/video-session")
            ->assertConflict()
            ->assertJsonPath('error.type', 'BookingNotEligibleForVideoSession');
    }

    private function bookingScenario(array $overrides = []): array
    {
        $professionalUser = User::factory()->professional()->create();
        $professionalProfile = ProfessionalProfile::factory()->create([
            'user_id' => $professionalUser->id,
        ]);
        $client = User::factory()->create();
        $modality = $overrides['modality'] ?? 'remota';
        $service = Service::factory()->create([
            'professional_id' => $professionalProfile->id,
            'modality' => $modality,
            'duration_minutes' => 60,
        ]);
        $booking = Booking::factory()->create([
            'service_id' => $service->id,
            'professional_id' => $professionalProfile->id,
            'client_id' => $client->id,
            'starts_at' => now()->addDays(3)->setTime(9, 0),
            'ends_at' => now()->addDays(3)->setTime(10, 0),
            'status' => BookingStatus::Confirmed,
            'confirmed_at' => now(),
            'modality' => $modality,
            'price_snapshot' => $service->price,
            'duration_minutes_snapshot' => $service->duration_minutes,
            ...$overrides,
        ]);

        return [$booking, $client, $professionalUser, $professionalProfile, $service];
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
