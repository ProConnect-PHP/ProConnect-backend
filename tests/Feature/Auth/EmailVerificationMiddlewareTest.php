<?php

namespace Tests\Feature\Auth;

use App\Enums\Booking\BookingStatus;
use App\Models\Availability\AvailabilityRule;
use App\Models\Booking\Booking;
use App\Models\Package\PackageProduct;
use App\Models\Service\Service;
use App\Models\User\ProfessionalProfile;
use App\Models\User\User;
use App\Models\Video\VideoSession;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmailVerificationMiddlewareTest extends TestCase
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

    public function test_unverified_professional_cannot_create_service(): void
    {
        [$professional] = $this->createProfessional(verified: false);

        $this->postJson('/api/v1/services', $this->servicePayload(), $this->authHeaders($professional))
            ->assertForbidden()
            ->assertJsonPath('error.type', 'EmailNotVerified')
            ->assertJsonPath('error.code', 'EMAIL_NOT_VERIFIED');
    }

    public function test_verified_professional_can_create_service(): void
    {
        [$professional] = $this->createProfessional();

        $this->postJson('/api/v1/services', $this->servicePayload(), $this->authHeaders($professional))
            ->assertCreated()
            ->assertJsonPath('service.name', 'Email verified service');
    }

    public function test_unverified_client_cannot_create_booking(): void
    {
        $client = User::factory()->unverified()->create();
        [, $professional] = $this->createProfessional();
        $service = $this->createBookableService($professional);

        $this->postJson("/api/v1/services/{$service->id}/bookings", [
            'starts_at' => '2026-06-15 09:00:00',
        ], $this->authHeaders($client))
            ->assertForbidden()
            ->assertJsonPath('error.type', 'EmailNotVerified');
    }

    public function test_verified_client_can_create_booking(): void
    {
        $client = User::factory()->create();
        [, $professional] = $this->createProfessional();
        $service = $this->createBookableService($professional);

        $this->postJson("/api/v1/services/{$service->id}/bookings", [
            'starts_at' => '2026-06-15 09:00:00',
        ], $this->authHeaders($client))
            ->assertCreated()
            ->assertJsonPath('booking.client_id', $client->id);
    }

    public function test_unverified_user_cannot_purchase_package(): void
    {
        $client = User::factory()->unverified()->create();
        [, $professional] = $this->createProfessional();
        $packageProduct = PackageProduct::factory()->active()->create([
            'professional_id' => $professional->id,
        ]);

        $this->postJson(
            "/api/v1/package-products/{$packageProduct->id}/purchase",
            [],
            $this->authHeaders($client),
        )
            ->assertForbidden()
            ->assertJsonPath('error.type', 'EmailNotVerified');
    }

    public function test_unverified_user_cannot_join_video_session(): void
    {
        $client = User::factory()->unverified()->create();
        [, $professional] = $this->createProfessional();
        $booking = Booking::factory()->paid()->create([
            'client_id' => $client->id,
            'professional_id' => $professional->id,
            'starts_at' => now()->subMinutes(5),
            'ends_at' => now()->addHour(),
        ]);
        $videoSession = VideoSession::factory()->forBooking($booking)->scheduled()->create();

        $this->postJson(
            "/api/v1/video-sessions/{$videoSession->id}/join",
            [],
            $this->authHeaders($client),
        )
            ->assertForbidden()
            ->assertJsonPath('error.type', 'EmailNotVerified');
    }

    public function test_unverified_user_cannot_create_review(): void
    {
        $client = User::factory()->unverified()->create();
        [, $professional] = $this->createProfessional();
        $booking = Booking::factory()->completed()->create([
            'client_id' => $client->id,
            'professional_id' => $professional->id,
            'status' => BookingStatus::Completed,
            'completed_at' => now(),
        ]);

        $this->postJson("/api/v1/bookings/{$booking->id}/review", [
            'rating' => 5,
            'comment' => 'Great session.',
        ], $this->authHeaders($client))
            ->assertForbidden()
            ->assertJsonPath('error.type', 'EmailNotVerified');
    }

    /**
     * @return array{User, ProfessionalProfile}
     */
    private function createProfessional(bool $verified = true): array
    {
        $factory = User::factory()->professional();

        if (! $verified) {
            $factory = $factory->unverified();
        }

        $user = $factory->create();
        $profile = ProfessionalProfile::factory()->create([
            'user_id' => $user->id,
        ]);

        return [$user, $profile];
    }

    private function createBookableService(ProfessionalProfile $professional): Service
    {
        $service = Service::factory()->active()->remote()->create([
            'professional_id' => $professional->id,
            'duration_minutes' => 60,
            'buffer_minutes' => 15,
        ]);

        AvailabilityRule::factory()->create([
            'service_id' => $service->id,
            'day_of_week' => 1,
            'start_time' => '09:00:00',
            'end_time' => '12:00:00',
            'is_active' => true,
        ]);

        return $service;
    }

    /**
     * @return array<string, mixed>
     */
    private function servicePayload(): array
    {
        return [
            'name' => 'Email verified service',
            'description' => 'Service used by email verification tests.',
            'price' => 1200,
            'duration_minutes' => 60,
            'modality' => 'remota',
            'address' => null,
            'link' => 'https://example.test/session',
            'latitude' => null,
            'longitude' => null,
            'max_bookings_per_client' => null,
            'min_reschedule_minutes' => 30,
            'buffer_minutes' => 15,
            'starts_at' => null,
            'ends_at' => null,
            'is_active' => true,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function authHeaders(User $user): array
    {
        return [
            'Authorization' => 'Bearer '.auth('user_jwt')->login($user),
            'Accept' => 'application/json',
        ];
    }
}
