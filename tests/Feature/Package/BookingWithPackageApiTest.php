<?php

namespace Tests\Feature\Package;

use App\Enums\Booking\BookingStatus;
use App\Enums\Package\ClientPackageStatus;
use App\Enums\Package\PackageSessionStatus;
use App\Models\Availability\AvailabilityRule;
use App\Models\Package\ClientPackage;
use App\Models\Package\PackageProduct;
use App\Models\Service\Service;
use App\Models\User\ProfessionalProfile;
use App\Models\User\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingWithPackageApiTest extends TestCase
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

    public function test_client_can_create_booking_using_own_package(): void
    {
        [$client, $service, $clientPackage] = $this->bookablePackageScenario();

        $response = $this
            ->withHeaders($this->authHeaders($client))
            ->postJson("/api/v1/services/{$service->id}/bookings", [
                'starts_at' => '2026-06-15 09:00:00',
                'client_package_id' => $clientPackage->id,
            ]);

        $response
            ->assertCreated()
            ->assertJsonPath('booking.status', BookingStatus::Pending->value)
            ->assertJsonPath('booking.client_package_id', $clientPackage->id)
            ->assertJsonPath('booking.payment_source', 'package')
            ->assertJsonPath('booking.package_session.status', PackageSessionStatus::Reserved->value);

        $this->assertDatabaseHas('package_sessions', [
            'client_package_id' => $clientPackage->id,
            'status' => PackageSessionStatus::Reserved->value,
        ]);
        $this->assertSame(1, $clientPackage->refresh()->used_sessions);
    }

    public function test_client_cannot_use_another_clients_package(): void
    {
        [$client, $service] = $this->bookablePackageScenario();
        $otherPackage = ClientPackage::factory()->forService($service)->active()->create();

        $this
            ->withHeaders($this->authHeaders($client))
            ->postJson("/api/v1/services/{$service->id}/bookings", [
                'starts_at' => '2026-06-15 09:00:00',
                'client_package_id' => $otherPackage->id,
            ])
            ->assertForbidden()
            ->assertJsonPath('error.type', 'Forbidden');
    }

    public function test_client_cannot_use_expired_or_depleted_package(): void
    {
        [$client, $service, $clientPackage] = $this->bookablePackageScenario();
        $clientPackage->update([
            'status' => ClientPackageStatus::Active,
            'expires_at' => now()->subDay(),
        ]);

        $this
            ->withHeaders($this->authHeaders($client))
            ->postJson("/api/v1/services/{$service->id}/bookings", [
                'starts_at' => '2026-06-15 09:00:00',
                'client_package_id' => $clientPackage->id,
            ])
            ->assertConflict()
            ->assertJsonPath('error.type', 'ClientPackageExpired');

        $clientPackage->update([
            'status' => ClientPackageStatus::Depleted,
            'expires_at' => now()->addDay(),
            'used_sessions' => $clientPackage->total_sessions,
        ]);

        $this
            ->withHeaders($this->authHeaders($client))
            ->postJson("/api/v1/services/{$service->id}/bookings", [
                'starts_at' => '2026-06-15 10:15:00',
                'client_package_id' => $clientPackage->id,
            ])
            ->assertConflict()
            ->assertJsonPath('error.type', 'ClientPackageDepleted');
    }

    public function test_client_cannot_use_package_for_another_service_or_professional(): void
    {
        [$client, $service, $clientPackage] = $this->bookablePackageScenario();
        $otherService = $this->createBookableService([
            'professional_id' => $service->professional_id,
        ]);
        $clientPackage->update([
            'service_id' => $otherService->id,
        ]);

        $this
            ->withHeaders($this->authHeaders($client))
            ->postJson("/api/v1/services/{$service->id}/bookings", [
                'starts_at' => '2026-06-15 09:00:00',
                'client_package_id' => $clientPackage->id,
            ])
            ->assertConflict()
            ->assertJsonPath('error.type', 'ClientPackageServiceMismatch');

        [, $otherServiceForOtherProfessional, $otherProfessionalPackage] = $this->bookablePackageScenario([
            'client_id' => $client->id,
        ]);

        $this
            ->withHeaders($this->authHeaders($client))
            ->postJson("/api/v1/services/{$service->id}/bookings", [
                'starts_at' => '2026-06-15 10:15:00',
                'client_package_id' => $otherProfessionalPackage->id,
            ])
            ->assertConflict()
            ->assertJsonPath('error.type', 'ClientPackageProfessionalMismatch');

        $this->assertNotSame($service->professional_id, $otherServiceForOtherProfessional->professional_id);
    }

    public function test_last_session_marks_package_depleted(): void
    {
        [$client, $service, $clientPackage] = $this->bookablePackageScenario([
            'total_sessions' => 1,
            'used_sessions' => 0,
        ]);

        $this
            ->withHeaders($this->authHeaders($client))
            ->postJson("/api/v1/services/{$service->id}/bookings", [
                'starts_at' => '2026-06-15 09:00:00',
                'client_package_id' => $clientPackage->id,
            ])
            ->assertCreated();

        $clientPackage->refresh();

        $this->assertSame(ClientPackageStatus::Depleted, $clientPackage->status);
        $this->assertSame(1, $clientPackage->used_sessions);
        $this->assertNotNull($clientPackage->depleted_at);
    }

    private function bookablePackageScenario(array $overrides = []): array
    {
        $client = isset($overrides['client_id'])
            ? User::query()->findOrFail($overrides['client_id'])
            : User::factory()->create();
        $service = $this->createBookableService();
        $product = PackageProduct::factory()
            ->forService($service)
            ->sessions($overrides['total_sessions'] ?? 4)
            ->active()
            ->create();
        $clientPackage = ClientPackage::factory()
            ->forPackageProduct($product)
            ->active()
            ->create([
                'client_id' => $client->id,
                'total_sessions' => $overrides['total_sessions'] ?? 4,
                'used_sessions' => $overrides['used_sessions'] ?? 0,
            ]);

        return [$client, $service, $clientPackage];
    }

    private function createBookableService(array $overrides = []): Service
    {
        $service = Service::factory()->create([
            'duration_minutes' => 60,
            'buffer_minutes' => 15,
            'is_active' => true,
            ...$overrides,
        ]);

        AvailabilityRule::factory()->create([
            'service_id' => $service->id,
            'day_of_week' => 1,
            'start_time' => '09:00',
            'end_time' => '12:00',
            'is_active' => true,
        ]);

        return $service;
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
