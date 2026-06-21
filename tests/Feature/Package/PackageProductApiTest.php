<?php

namespace Tests\Feature\Package;

use App\Models\Package\PackageProduct;
use App\Models\Service\Service;
use App\Models\User\ProfessionalProfile;
use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PackageProductApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_professional_can_create_package_product(): void
    {
        [$user, $profile] = $this->professional();

        $this
            ->withHeaders($this->authHeaders($user))
            ->postJson('/api/v1/professional/package-products', [
                'name' => 'Pack 4 sesiones online',
                'sessions_count' => 4,
                'price' => 5600,
                'validity_days' => 60,
            ])
            ->assertCreated()
            ->assertJsonPath('package_product.service_id', null)
            ->assertJsonPath('package_product.sessions_count', 4)
            ->assertJsonPath('package_product.currency', 'UYU');

        $this->assertDatabaseHas('package_products', [
            'professional_id' => $profile->id,
            'service_id' => null,
            'name' => 'Pack 4 sesiones online',
            'sessions_count' => 4,
            'currency' => 'UYU',
        ]);
    }

    public function test_professional_cannot_create_package_for_foreign_service(): void
    {
        [$user] = $this->professional();
        [, $otherProfile] = $this->professional();

        $foreignService = Service::factory()->create([
            'professional_id' => $otherProfile->id,
        ]);

        $this
            ->withHeaders($this->authHeaders($user))
            ->postJson('/api/v1/professional/package-products', [
                'service_id' => $foreignService->id,
                'name' => 'Pack ajeno',
                'sessions_count' => 4,
                'price' => 5600,
                'validity_days' => 60,
            ])
            ->assertForbidden()
            ->assertJsonPath('error.type', 'Forbidden');
    }

    public function test_client_cannot_create_package_product(): void
    {
        $client = User::factory()->create();

        $this
            ->withHeaders($this->authHeaders($client))
            ->postJson('/api/v1/professional/package-products', $this->validPayload())
            ->assertForbidden()
            ->assertJsonPath('error.type', 'Forbidden');
    }


    public function test_professional_can_update_own_package_product(): void
    {
        [$user, $profile] = $this->professional();

        $packageProduct = PackageProduct::factory()->create([
            'professional_id' => $profile->id,
            'name' => 'Pack viejo',
            'is_active' => true,
        ]);

        $this
            ->withHeaders($this->authHeaders($user))
            ->putJson("/api/v1/professional/package-products/{$packageProduct->id}", [
                'name' => 'Pack actualizado',
                'is_active' => false,
            ])
            ->assertOk()
            ->assertJsonPath('package_product.name', 'Pack actualizado')
            ->assertJsonPath('package_product.is_active', false);

        $this->assertDatabaseHas('package_products', [
            'id' => $packageProduct->id,
            'name' => 'Pack actualizado',
            'is_active' => false,
        ]);
    }

    public function test_professional_cannot_update_foreign_package_product(): void
    {
        [$user] = $this->professional();
        [, $otherProfile] = $this->professional();

        $packageProduct = PackageProduct::factory()->create([
            'professional_id' => $otherProfile->id,
        ]);

        $this
            ->withHeaders($this->authHeaders($user))
            ->putJson("/api/v1/professional/package-products/{$packageProduct->id}", [
                'name' => 'Intento ajeno',
            ])
            ->assertForbidden()
            ->assertJsonPath('error.type', 'Forbidden');
    }

    public function test_professional_can_delete_own_package_product(): void
    {
        [$user, $profile] = $this->professional();

        $packageProduct = PackageProduct::factory()->create([
            'professional_id' => $profile->id,
        ]);

        $this
            ->withHeaders($this->authHeaders($user))
            ->deleteJson("/api/v1/professional/package-products/{$packageProduct->id}")
            ->assertOk();

        $this->assertSoftDeleted('package_products', [
            'id' => $packageProduct->id,
        ]);
    }

    public function test_sessions_count_and_price_are_validated(): void
    {
        [$user] = $this->professional();

        $this
            ->withHeaders($this->authHeaders($user))
            ->postJson('/api/v1/professional/package-products', $this->validPayload([
                'sessions_count' => 0,
                'price' => -1,
            ]))
            ->assertUnprocessable()
            ->assertJsonPath('error.type', 'ValidationError');
    }

    private function professional(): array
    {
        $user = User::factory()->professional()->create();

        $profile = ProfessionalProfile::factory()->create([
            'user_id' => $user->id,
        ]);

        return [$user, $profile];
    }

    private function validPayload(array $overrides = []): array
    {
        return [
            'service_id' => null,
            'name' => 'Pack 4 sesiones online',
            'sessions_count' => 4,
            'price' => 5600,
            'validity_days' => 60,
            ...$overrides,
        ];
    }

    private function authHeaders(User $user): array
    {
        return [
            'Authorization' => 'Bearer '.auth('user_jwt')->login($user),
            'Accept' => 'application/json',
        ];
    }
}
