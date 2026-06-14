<?php

namespace Tests\Feature\Video;

use App\Actions\Video\EnsureVideoSessionForBookingAction;
use App\Enums\Booking\BookingStatus;
use App\Models\Booking\Booking;
use App\Models\Package\ClientPackage;
use App\Models\Package\PackageSession;
use App\Models\Service\Service;
use App\Models\User\ProfessionalProfile;
use App\Models\User\User;
use Carbon\Carbon;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GenerateLiveKitJoinTokenApiTest extends TestCase
{
    use RefreshDatabase;

    private const LIVEKIT_SECRET = 'proconnect_test_livekit_secret_at_least_32_chars';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.livekit', [
            'url' => 'ws://livekit.test:7880',
            'api_key' => 'proconnect_test_key',
            'api_secret' => self::LIVEKIT_SECRET,
            'token_ttl_seconds' => 3600,
        ]);
        config()->set('proconnect.video.provider', 'livekit');
        Carbon::setTestNow('2026-06-15 08:50:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_guest_cannot_request_livekit_credentials(): void
    {
        [$booking] = $this->bookingScenario();

        $this->postJson($this->endpoint($booking))
            ->assertUnauthorized()
            ->assertJsonPath('error.type', 'Unauthorized');
    }

    public function test_client_receives_valid_livekit_credentials(): void
    {
        [$booking, $client] = $this->bookingScenario();
        $identity = "user_{$client->id}_booking_{$booking->id}";
        $roomName = "booking-{$booking->id}";

        $response = $this
            ->withHeaders($this->authHeaders($client))
            ->postJson($this->endpoint($booking))
            ->assertOk()
            ->assertJsonPath('data.url', 'ws://livekit.test:7880')
            ->assertJsonPath('data.roomName', $roomName)
            ->assertJsonPath('data.participantIdentity', $identity)
            ->assertJsonPath('data.participantName', $client->name)
            ->assertJson(fn ($json) => $json->whereType('data.token', 'string')->etc());

        $claims = JWT::decode(
            $response->json('data.token'),
            new Key(self::LIVEKIT_SECRET, 'HS256')
        );

        $this->assertSame($identity, $claims->sub);
        $this->assertSame($client->name, $claims->name);
        $this->assertSame($roomName, $claims->video->room);
        $this->assertTrue($claims->video->roomJoin);
        $this->assertTrue($claims->video->canPublish);
        $this->assertTrue($claims->video->canSubscribe);
        $this->assertSame('proconnect_test_key', $claims->iss);
        $this->assertSame(3600, $claims->exp - $claims->iat);

        $this->assertDatabaseHas('video_sessions', [
            'booking_id' => $booking->id,
        ]);
        $this->assertSame(BookingStatus::Paid, $booking->refresh()->status);
    }

    public function test_professional_owner_receives_livekit_credentials(): void
    {
        [$booking, , $professionalUser] = $this->bookingScenario();

        $this
            ->withHeaders($this->authHeaders($professionalUser))
            ->postJson($this->endpoint($booking))
            ->assertOk()
            ->assertJsonPath(
                'data.participantIdentity',
                "user_{$professionalUser->id}_booking_{$booking->id}"
            )
            ->assertJsonPath('data.participantName', $professionalUser->name);
    }

    public function test_unrelated_user_cannot_request_livekit_credentials(): void
    {
        [$booking] = $this->bookingScenario();
        $otherUser = User::factory()->create();

        $this
            ->withHeaders($this->authHeaders($otherUser))
            ->postJson($this->endpoint($booking))
            ->assertForbidden()
            ->assertJsonPath('error.type', 'Forbidden');
    }

    public function test_unrelated_professional_cannot_request_livekit_credentials(): void
    {
        [$booking] = $this->bookingScenario();
        $otherProfessional = User::factory()->professional()->create();
        ProfessionalProfile::factory()->create([
            'user_id' => $otherProfessional->id,
        ]);

        $this
            ->withHeaders($this->authHeaders($otherProfessional))
            ->postJson($this->endpoint($booking))
            ->assertForbidden()
            ->assertJsonPath('error.type', 'Forbidden');
    }

    public function test_booking_status_must_allow_video_session(): void
    {
        [$booking, $client] = $this->bookingScenario([
            'status' => BookingStatus::Pending,
            'confirmed_at' => null,
        ]);

        $this
            ->withHeaders($this->authHeaders($client))
            ->postJson($this->endpoint($booking))
            ->assertForbidden()
            ->assertJsonPath(
                'error.type',
                'BookingNotEligibleForVideoSession'
            );
    }

    public function test_in_person_booking_cannot_request_livekit_credentials(): void
    {
        [$booking, $client] = $this->bookingScenario([
            'modality' => 'presencial',
        ]);

        $this
            ->withHeaders($this->authHeaders($client))
            ->postJson($this->endpoint($booking))
            ->assertUnprocessable()
            ->assertJsonPath(
                'error.type',
                'VideoSessionNotAllowedForModality'
            );
    }

    public function test_closed_booking_statuses_cannot_request_livekit_credentials(): void
    {
        foreach ([
            BookingStatus::Cancelled,
            BookingStatus::Completed,
            BookingStatus::NoShow,
        ] as $status) {
            [$booking, $client] = $this->bookingScenario([
                'status' => $status,
            ]);

            $this
                ->withHeaders($this->authHeaders($client))
                ->postJson($this->endpoint($booking))
                ->assertForbidden()
                ->assertJsonPath(
                    'error.type',
                    'BookingNotEligibleForVideoSession'
                );
        }
    }

    public function test_paid_and_in_progress_bookings_are_allowed(): void
    {
        foreach ([
            [BookingStatus::Paid, 'remota'],
            [BookingStatus::InProgress, 'remota'],
        ] as [$status, $modality]) {
            [$booking, $client] = $this->bookingScenario([
                'status' => $status,
                'modality' => $modality,
            ]);

            $this
                ->withHeaders($this->authHeaders($client))
                ->postJson($this->endpoint($booking))
                ->assertOk();
        }
    }

    public function test_unpaid_confirmed_booking_cannot_request_livekit_credentials(): void
    {
        [$booking, $client] = $this->bookingScenario([
            'status' => BookingStatus::Confirmed,
            'paid_at' => null,
        ]);

        $this
            ->withHeaders($this->authHeaders($client))
            ->postJson($this->endpoint($booking))
            ->assertForbidden()
            ->assertJsonPath('error.type', 'VideoSessionPaymentRequired');
    }

    public function test_package_covered_booking_can_request_livekit_credentials(): void
    {
        [$booking, $client] = $this->bookingScenario([
            'status' => BookingStatus::Confirmed,
            'paid_at' => null,
        ], provisionVideo: false);
        $clientPackage = ClientPackage::factory()
            ->forService($booking->service)
            ->active()
            ->create([
                'client_id' => $client->id,
            ]);
        $booking->update(['client_package_id' => $clientPackage->id]);
        PackageSession::factory()
            ->forClientPackage($clientPackage)
            ->forBooking($booking)
            ->reserved()
            ->create();
        app(EnsureVideoSessionForBookingAction::class)($booking->refresh());

        $this
            ->withHeaders($this->authHeaders($client))
            ->postJson($this->endpoint($booking))
            ->assertOk()
            ->assertJsonPath(
                'data.roomName',
                "booking-{$booking->id}"
            );
    }

    public function test_paid_booking_outside_join_window_is_blocked(): void
    {
        [$booking, $client] = $this->bookingScenario();
        Carbon::setTestNow('2026-06-16 12:00:00');

        $this
            ->withHeaders($this->authHeaders($client))
            ->postJson($this->endpoint($booking))
            ->assertForbidden()
            ->assertJsonPath('error.type', 'VideoSessionJoinWindowClosed');
    }

    public function test_missing_booking_returns_not_found(): void
    {
        $client = User::factory()->create();

        $this
            ->withHeaders($this->authHeaders($client))
            ->postJson('/api/v1/video-sessions/bookings/00000000-0000-0000-0000-000000000000/join')
            ->assertNotFound()
            ->assertJsonPath('error.type', 'NotFound');
    }

    private function bookingScenario(
        array $overrides = [],
        bool $provisionVideo = true,
    ): array {
        $professionalUser = User::factory()->professional()->create();
        $professional = ProfessionalProfile::factory()->create([
            'user_id' => $professionalUser->id,
        ]);
        $client = User::factory()->create();
        $modality = $overrides['modality'] ?? 'remota';
        $service = Service::factory()->create([
            'professional_id' => $professional->id,
            'modality' => $modality,
        ]);
        $booking = Booking::factory()->create([
            'service_id' => $service->id,
            'professional_id' => $professional->id,
            'client_id' => $client->id,
            'starts_at' => '2026-06-15 09:00:00',
            'ends_at' => '2026-06-15 10:00:00',
            'status' => BookingStatus::Paid,
            'confirmed_at' => now(),
            'paid_at' => now(),
            'modality' => $modality,
            ...$overrides,
        ]);

        if ($provisionVideo && $booking->canProvisionVideoSession()) {
            app(EnsureVideoSessionForBookingAction::class)($booking);
        }

        return [$booking, $client, $professionalUser, $professional];
    }

    private function endpoint(Booking $booking): string
    {
        return "/api/v1/video-sessions/bookings/{$booking->id}/join";
    }

    private function authHeaders(User $user): array
    {
        return [
            'Authorization' => 'Bearer '.auth('user_jwt')->login($user),
            'Accept' => 'application/json',
        ];
    }
}
