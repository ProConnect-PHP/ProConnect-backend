<?php

namespace Tests\Feature\Package;

use App\Enums\Package\ClientPackageStatus;
use App\Models\Package\PackageProduct;
use App\Models\Service\Service;
use App\Models\User\ProfessionalProfile;
use App\Models\User\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchasePackageApiTest extends TestCase
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

    public function test_client_can_purchase_active_package(): void
    {
        $client = User::factory()->create();
        $packageProduct = PackageProduct::factory()->active()->create([
            'sessions_count' => 4,
            'price' => 5600,
            'validity_days' => 60,
        ]);

        $response = $this
            ->withHeaders($this->authHeaders($client))
            ->postJson("/api/v1/package-products/{$packageProduct->id}/purchase", [
                'metadata' => ['source' => 'test'],
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('client_package.status', ClientPackageStatus::Active->value)
            ->assertJsonPath('client_package.total_sessions', 4)
            ->assertJsonPath('client_package.used_sessions', 0)
            ->assertJsonPath('client_package.price_snapshot', 5600)
            ->assertJsonPath('client_package.expires_at', '2026-07-31 12:00:00');
    }

    public function test_guest_cannot_purchase_package(): void
    {
        $packageProduct = PackageProduct::factory()->active()->create();

        $this
            ->postJson("/api/v1/package-products/{$packageProduct->id}/purchase")
            ->assertUnauthorized()
            ->assertJsonPath('error.type', 'Unauthorized');
    }

    public function test_client_cannot_purchase_inactive_package(): void
    {
        $client = User::factory()->create();
        $packageProduct = PackageProduct::factory()->inactive()->create();

        $this
            ->withHeaders($this->authHeaders($client))
            ->postJson("/api/v1/package-products/{$packageProduct->id}/purchase")
            ->assertConflict()
            ->assertJsonPath('error.type', 'PackageNotAvailable');
    }

    public function test_professional_cannot_purchase_own_package(): void
    {
        $professionalUser = User::factory()->professional()->create();
        $profile = ProfessionalProfile::factory()->create([
            'user_id' => $professionalUser->id,
        ]);
        $service = Service::factory()->create([
            'professional_id' => $profile->id,
        ]);
        $packageProduct = PackageProduct::factory()->forService($service)->active()->create();

        $this
            ->withHeaders($this->authHeaders($professionalUser))
            ->postJson("/api/v1/package-products/{$packageProduct->id}/purchase")
            ->assertForbidden()
            ->assertJsonPath('error.type', 'Forbidden');
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
