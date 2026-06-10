<?php

namespace Tests\Feature\Security;

use App\Enums\Booking\BookingStatus;
use App\Models\Booking\Booking;
use App\Models\Service\Service;
use App\Models\User\ProfessionalProfile;
use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RateLimitingTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_public_api_is_rate_limited(): void
    {
        config()->set('security.rate_limits.api_public.guest', 2);

        $this->getJson('/api/v1/public/services')->assertOk();
        $this->getJson('/api/v1/public/services')->assertOk();
        $this->getJson('/api/v1/public/services')
            ->assertTooManyRequests()
            ->assertJsonPath('error.type', 'TooManyRequests');
    }

    public function test_public_api_limits_are_higher_for_each_authenticated_role(): void
    {
        config()->set('security.rate_limits.api_public', [
            'guest' => 1,
            'client' => 2,
            'professional' => 3,
        ]);

        $client = User::factory()->create();
        $professional = User::factory()->professional()->create();

        $clientRequest = $this->withHeaders($this->authHeaders($client));
        $clientRequest->getJson('/api/v1/public/services')->assertOk();
        $clientRequest->getJson('/api/v1/public/services')->assertOk();
        $clientRequest->getJson('/api/v1/public/services')->assertTooManyRequests();

        $professionalRequest = $this->withHeaders($this->authHeaders($professional));
        $professionalRequest->getJson('/api/v1/public/services')->assertOk();
        $professionalRequest->getJson('/api/v1/public/services')->assertOk();
        $professionalRequest->getJson('/api/v1/public/services')->assertOk();
        $professionalRequest->getJson('/api/v1/public/services')->assertTooManyRequests();
    }

    public function test_login_blocks_excessive_attempts(): void
    {
        config()->set('security.rate_limits.auth_login', 2);

        $payload = [
            'email' => 'rate-limit@example.test',
            'password' => 'invalid-password',
        ];

        $this->postJson('/api/v1/auth/login', $payload)->assertUnauthorized();
        $this->postJson('/api/v1/auth/login', $payload)->assertUnauthorized();
        $this->postJson('/api/v1/auth/login', $payload)
            ->assertTooManyRequests()
            ->assertJsonPath('error.type', 'TooManyRequests');
    }

    public function test_booking_write_blocks_excessive_requests(): void
    {
        config()->set('security.rate_limits.booking_write.client', 2);

        [, $client, , $service] = $this->bookingScenario();
        $request = $this->withHeaders($this->authHeaders($client));
        $endpoint = "/api/v1/services/{$service->id}/bookings";

        $request->postJson($endpoint, [])->assertUnprocessable();
        $request->postJson($endpoint, [])->assertUnprocessable();
        $request->postJson($endpoint, [])
            ->assertTooManyRequests()
            ->assertJsonPath('error.type', 'TooManyRequests');
    }

    public function test_video_join_blocks_excessive_token_generation(): void
    {
        config()->set('security.rate_limits.video_join.client', 2);
        config()->set('services.livekit', [
            'url' => 'ws://livekit.test:7880',
            'api_key' => 'proconnect_test_key',
            'api_secret' => 'proconnect_test_livekit_secret_at_least_32_chars',
            'token_ttl_seconds' => 3600,
        ]);

        [$booking, $client] = $this->bookingScenario();
        $request = $this->withHeaders($this->authHeaders($client));
        $endpoint = "/api/v1/video-sessions/bookings/{$booking->id}/join";

        $request->postJson($endpoint)->assertOk();
        $request->postJson($endpoint)->assertOk();
        $request->postJson($endpoint)
            ->assertTooManyRequests()
            ->assertJsonPath('error.type', 'TooManyRequests');
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
            'modality' => 'remota',
        ]);
        $booking = Booking::factory()->create([
            'service_id' => $service->id,
            'professional_id' => $professional->id,
            'client_id' => $client->id,
            'status' => BookingStatus::Confirmed,
            'confirmed_at' => now(),
            'modality' => 'remota',
        ]);

        return [$booking, $client, $professionalUser, $service];
    }

    private function authHeaders(User $user): array
    {
        return [
            'Authorization' => 'Bearer '.auth('user_jwt')->login($user),
            'Accept' => 'application/json',
        ];
    }
}
