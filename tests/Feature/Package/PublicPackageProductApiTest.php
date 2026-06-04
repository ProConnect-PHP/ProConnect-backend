<?php

namespace Tests\Feature\Package;

use App\Models\Package\PackageProduct;
use App\Models\Service\Service;
use App\Models\User\ProfessionalProfile;
use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicPackageProductApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_index_lists_only_active_packages(): void
    {
        $active = PackageProduct::factory()->active()->create();
        $inactive = PackageProduct::factory()->inactive()->create();

        $response = $this->getJson('/api/v1/public/package-products');

        $response
            ->assertOk()
            ->assertJsonPath('package_products.0.id', $active->id);

        $ids = collect($response->json('package_products'))->pluck('id');
        $this->assertFalse($ids->contains($inactive->id));
    }

    public function test_service_endpoint_lists_packages_for_that_service(): void
    {
        $profile = ProfessionalProfile::factory()->create();
        $service = Service::factory()->create([
            'professional_id' => $profile->id,
        ]);
        $otherService = Service::factory()->create([
            'professional_id' => $profile->id,
        ]);
        $packageProduct = PackageProduct::factory()->forService($service)->active()->create();
        $otherPackage = PackageProduct::factory()->forService($otherService)->active()->create();

        $response = $this->getJson("/api/v1/services/{$service->id}/package-products");

        $response
            ->assertOk()
            ->assertJsonPath('package_products.0.id', $packageProduct->id)
            ->assertJsonPath('meta.total', 1);

        $this->assertNotSame($otherPackage->id, $response->json('package_products.0.id'));
    }

    public function test_public_resource_includes_service_and_professional_data(): void
    {
        $professionalUser = User::factory()->professional()->create([
            'name' => 'Profesional Demo',
        ]);
        $profile = ProfessionalProfile::factory()->create([
            'user_id' => $professionalUser->id,
        ]);
        $service = Service::factory()->create([
            'professional_id' => $profile->id,
            'name' => 'Servicio Demo',
        ]);
        $packageProduct = PackageProduct::factory()->forService($service)->active()->create();

        $this
            ->getJson("/api/v1/public/package-products/{$packageProduct->id}")
            ->assertOk()
            ->assertJsonPath('package_product.service.name', 'Servicio Demo')
            ->assertJsonPath('package_product.professional.user.name', 'Profesional Demo');
    }
}
