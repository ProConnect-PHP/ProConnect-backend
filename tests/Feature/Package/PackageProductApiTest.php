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
        [$professionalUser, $profile] = $this->professionalWithProfile();
        $service = Service::factory()->create([
            'professional_id' => $profile->id,
        ]);

        $response = $this
            ->withHeaders($this->authHeaders($professionalUser))
            ->postJson('/api/v1/professional/package-products', [
                'service_id' => $service->id,
                'name' => 'Pack 4 sesiones online',
                'sessions_count' => 4,
                'price' => 5600,
                'validity_days' => 60,
            ]);

        $response
            ->assertCreated()
            ->assertJsonPath('package_product.service_id', $service->id)
            ->assertJsonPath('package_product.sessions_count', 4)
            ->assertJsonPath('package_product.price', 5600);
    }

    public function test_client_cannot_create_package_product(): void
    {
        $client = User::factory()->create();

        $this
            ->withHeaders($this->authHeaders($client))
            ->postJson('/api/v1/professional/package-products', [
                'name' => 'Pack 4 sesiones online',
                'sessions_count' => 4,
                'price' => 5600,
            ])
            ->assertForbidden()
            ->assertJsonPath('error.type', 'Forbidden');
    }

    public function test_professional_cannot_create_package_for_another_professionals_service(): void
    {
        [$professionalUser] = $this->professionalWithProfile();
        [, $otherProfile] = $this->professionalWithProfile();
        $service = Service::factory()->create([
            'professional_id' => $otherProfile->id,
        ]);

        $this
            ->withHeaders($this->authHeaders($professionalUser))
            ->postJson('/api/v1/professional/package-products', [
                'service_id' => $service->id,
                'name' => 'Pack ajeno',
                'sessions_count' => 4,
                'price' => 5600,
            ])
            ->assertForbidden()
            ->assertJsonPath('error.type', 'Forbidden');
    }

    public function test_professional_can_update_own_package_product(): void
    {
        [$professionalUser, $profile] = $this->professionalWithProfile();
        $packageProduct = PackageProduct::factory()->create([
            'professional_id' => $profile->id,
            'name' => 'Pack viejo',
        ]);

        $this
            ->withHeaders($this->authHeaders($professionalUser))
            ->putJson("/api/v1/professional/package-products/{$packageProduct->id}", [
                'name' => 'Pack actualizado',
                'is_active' => false,
            ])
            ->assertOk()
            ->assertJsonPath('package_product.name', 'Pack actualizado')
            ->assertJsonPath('package_product.is_active', false);
    }

    public function test_professional_cannot_update_another_professionals_package(): void
    {
        [$professionalUser] = $this->professionalWithProfile();
        [, $otherProfile] = $this->professionalWithProfile();
        $packageProduct = PackageProduct::factory()->create([
            'professional_id' => $otherProfile->id,
        ]);

        $this
            ->withHeaders($this->authHeaders($professionalUser))
            ->putJson("/api/v1/professional/package-products/{$packageProduct->id}", [
                'name' => 'Intento ajeno',
            ])
            ->assertForbidden()
            ->assertJsonPath('error.type', 'Forbidden');
    }

    public function test_professional_can_delete_own_package_product(): void
    {
        [$professionalUser, $profile] = $this->professionalWithProfile();
        $packageProduct = PackageProduct::factory()->create([
            'professional_id' => $profile->id,
        ]);

        $this
            ->withHeaders($this->authHeaders($professionalUser))
            ->deleteJson("/api/v1/professional/package-products/{$packageProduct->id}")
            ->assertOk();

        $this->assertSoftDeleted('package_products', [
            'id' => $packageProduct->id,
        ]);
    }

    public function test_sessions_count_and_price_are_validated(): void
    {
        [$professionalUser] = $this->professionalWithProfile();

        $this
            ->withHeaders($this->authHeaders($professionalUser))
            ->postJson('/api/v1/professional/package-products', [
                'name' => 'Pack invalido',
                'sessions_count' => 0,
                'price' => -1,
            ])
            ->assertUnprocessable()
            ->assertJsonPath('error.type', 'ValidationError');
    }

    private function professionalWithProfile(): array
    {
        $user = User::factory()->professional()->create();
        $profile = ProfessionalProfile::factory()->create([
            'user_id' => $user->id,
        ]);

        return [$user, $profile];
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
