<?php

namespace Tests\Feature\Package;

use App\Models\Package\ClientPackage;
use App\Models\Package\PackageProduct;
use App\Models\Service\Service;
use App\Models\User\ProfessionalProfile;
use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class PackagePolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_professional_owner_can_manage_package_product(): void
    {
        [$professionalUser, $profile] = $this->professionalWithProfile();
        $packageProduct = PackageProduct::factory()->create([
            'professional_id' => $profile->id,
        ]);

        $this->assertTrue(Gate::forUser($professionalUser)->allows('manage', $packageProduct));
        $this->assertTrue(Gate::forUser($professionalUser)->allows('update', $packageProduct));
        $this->assertTrue(Gate::forUser($professionalUser)->allows('delete', $packageProduct));
    }

    public function test_other_professional_cannot_manage_package_product(): void
    {
        [$professionalUser] = $this->professionalWithProfile();
        [, $otherProfile] = $this->professionalWithProfile();
        $packageProduct = PackageProduct::factory()->create([
            'professional_id' => $otherProfile->id,
        ]);

        $this->assertFalse(Gate::forUser($professionalUser)->allows('manage', $packageProduct));
    }

    public function test_client_owner_can_view_and_use_client_package(): void
    {
        [$clientPackage, $client] = $this->clientPackageScenario();

        $this->assertTrue(Gate::forUser($client)->allows('view', $clientPackage));
        $this->assertTrue(Gate::forUser($client)->allows('use', $clientPackage));
    }

    public function test_other_client_cannot_view_or_use_client_package(): void
    {
        [$clientPackage] = $this->clientPackageScenario();
        $otherClient = User::factory()->create();

        $this->assertFalse(Gate::forUser($otherClient)->allows('view', $clientPackage));
        $this->assertFalse(Gate::forUser($otherClient)->allows('use', $clientPackage));
    }

    public function test_professional_owner_can_view_sold_client_package(): void
    {
        [$clientPackage,, $professionalUser] = $this->clientPackageScenario();

        $this->assertTrue(Gate::forUser($professionalUser)->allows('view', $clientPackage));
        $this->assertFalse(Gate::forUser($professionalUser)->allows('use', $clientPackage));
    }

    private function clientPackageScenario(): array
    {
        $client = User::factory()->create();
        [$professionalUser, $profile] = $this->professionalWithProfile();
        $service = Service::factory()->create([
            'professional_id' => $profile->id,
        ]);
        $product = PackageProduct::factory()->forService($service)->create();
        $clientPackage = ClientPackage::factory()
            ->forPackageProduct($product)
            ->create([
                'client_id' => $client->id,
            ]);

        return [$clientPackage, $client, $professionalUser];
    }

    private function professionalWithProfile(): array
    {
        $user = User::factory()->professional()->create();
        $profile = ProfessionalProfile::factory()->create([
            'user_id' => $user->id,
        ]);

        return [$user, $profile];
    }
}
