<?php

namespace Tests\Feature\ProfessionalProfile;

use App\Enums\UserRole;
use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfessionalProfileActivationTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_can_create_professional_profile(): void
    {
        $client = User::factory()->create();

        $this->postJson('/api/v1/professional-profile', [
            'bio' => 'Consultora independiente.',
        ], $this->authHeaders($client))
            ->assertCreated();

        $this->assertDatabaseHas('professional_profiles', [
            'user_id' => $client->id,
            'bio' => 'Consultora independiente.',
        ]);
        $this->assertSame(UserRole::Professional, $client->refresh()->role);
    }

    public function test_creating_professional_profile_returns_updated_user(): void
    {
        $client = User::factory()->create();

        $this->postJson('/api/v1/professional-profile', [
            'bio' => 'Especialista en producto digital.',
        ], $this->authHeaders($client))
            ->assertCreated()
            ->assertJsonPath('professional_profile.bio', 'Especialista en producto digital.')
            ->assertJsonPath('user.id', $client->id)
            ->assertJsonPath('user.role', UserRole::Professional->value)
            ->assertJsonPath('user.has_professional_profile', true);
    }

    public function test_user_cannot_create_duplicate_professional_profile(): void
    {
        $client = User::factory()->create();
        $headers = $this->authHeaders($client);

        $this->postJson('/api/v1/professional-profile', [
            'bio' => 'Primer perfil.',
        ], $headers)->assertCreated();

        $this->postJson('/api/v1/professional-profile', [
            'bio' => 'Perfil duplicado.',
        ], $headers)
            ->assertConflict()
            ->assertJsonPath('error.type', 'ProfessionalProfileAlreadyExists');

        $this->assertDatabaseCount('professional_profiles', 1);
    }

    public function test_professional_keeps_client_capabilities_after_activation(): void
    {
        $client = User::factory()->create();
        $headers = $this->authHeaders($client);

        $this->postJson('/api/v1/professional-profile', [
            'bio' => 'Perfil profesional activo.',
        ], $headers)->assertCreated();

        $this->getJson('/api/v1/bookings/my', $headers)
            ->assertOk()
            ->assertJsonPath('bookings', []);
    }

    public function test_activated_user_can_access_professional_routes(): void
    {
        $client = User::factory()->create();
        $headers = $this->authHeaders($client);

        $this->postJson('/api/v1/professional-profile', [
            'bio' => 'Perfil profesional activo.',
        ], $headers)->assertCreated();

        $this->getJson('/api/v1/services/my', $headers)
            ->assertOk();
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
