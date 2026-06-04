<?php

namespace Tests\Feature\Package;

use App\Models\Package\ClientPackage;
use App\Models\Package\PackageProduct;
use App\Models\Service\Service;
use App\Models\User\ProfessionalProfile;
use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientPackageApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_lists_only_their_packages(): void
    {
        $client = User::factory()->create();
        $otherClient = User::factory()->create();
        $clientPackage = ClientPackage::factory()->create([
            'client_id' => $client->id,
            'total_sessions' => 4,
            'used_sessions' => 1,
        ]);
        $otherPackage = ClientPackage::factory()->create([
            'client_id' => $otherClient->id,
        ]);

        $response = $this
            ->withHeaders($this->authHeaders($client))
            ->getJson('/api/v1/client-packages/my');

        $response
            ->assertOk()
            ->assertJsonPath('client_packages.0.id', $clientPackage->id)
            ->assertJsonPath('client_packages.0.remaining_sessions', 3)
            ->assertJsonPath('meta.total', 1);

        $this->assertNotSame($otherPackage->id, $response->json('client_packages.0.id'));
    }

    public function test_client_cannot_show_another_clients_package(): void
    {
        $client = User::factory()->create();
        $otherPackage = ClientPackage::factory()->create();

        $this
            ->withHeaders($this->authHeaders($client))
            ->getJson("/api/v1/client-packages/{$otherPackage->id}")
            ->assertForbidden()
            ->assertJsonPath('error.type', 'Forbidden');
    }

    public function test_professional_lists_only_sold_packages(): void
    {
        [$professionalUser, $profile] = $this->professionalWithProfile();
        [, $otherProfile] = $this->professionalWithProfile();
        $soldPackage = $this->clientPackageForProfessional($profile);
        $otherSoldPackage = $this->clientPackageForProfessional($otherProfile);

        $response = $this
            ->withHeaders($this->authHeaders($professionalUser))
            ->getJson('/api/v1/professional/client-packages');

        $response
            ->assertOk()
            ->assertJsonPath('client_packages.0.id', $soldPackage->id)
            ->assertJsonPath('meta.total', 1);

        $this->assertNotSame($otherSoldPackage->id, $response->json('client_packages.0.id'));
    }

    private function clientPackageForProfessional(ProfessionalProfile $professional): ClientPackage
    {
        $service = Service::factory()->create([
            'professional_id' => $professional->id,
        ]);
        $product = PackageProduct::factory()->forService($service)->create();

        return ClientPackage::factory()
            ->forPackageProduct($product)
            ->create();
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
