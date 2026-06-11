<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Models\User\ProfessionalProfile;
use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_private_endpoint(): void
    {
        $this->getJson('/api/v1/me')
            ->assertUnauthorized()
            ->assertJsonPath('error.type', 'Unauthorized');
    }

    public function test_client_cannot_access_professional_route(): void
    {
        $client = User::factory()->create();

        $this->withHeaders($this->authHeaders($client))
            ->getJson('/api/v1/services/my')
            ->assertForbidden()
            ->assertJsonPath('error.type', 'Forbidden');
    }

    public function test_professional_can_access_client_capable_route(): void
    {
        $professional = User::factory()->professional()->create();
        ProfessionalProfile::factory()->create([
            'user_id' => $professional->id,
        ]);

        $this->withHeaders($this->authHeaders($professional))
            ->getJson('/api/v1/bookings/my')
            ->assertOk()
            ->assertJsonPath('bookings', []);
    }

    public function test_valid_role_can_access_its_route(): void
    {
        $professional = User::factory()->professional()->create();
        ProfessionalProfile::factory()->create([
            'user_id' => $professional->id,
        ]);

        $this->withHeaders($this->authHeaders($professional))
            ->getJson('/api/v1/services/my')
            ->assertOk();
    }

    public function test_user_role_helpers_and_cast_are_explicit(): void
    {
        $client = User::factory()->create();
        $professional = User::factory()->professional()->create();

        $this->assertSame(UserRole::Client, $client->role);
        $this->assertTrue($client->isClient());
        $this->assertTrue($client->hasRole('client'));
        $this->assertTrue($client->hasAnyRole(['professional', UserRole::Client]));

        $this->assertSame(UserRole::Professional, $professional->role);
        $this->assertTrue($professional->isProfessional());
        $this->assertFalse($professional->hasRole(UserRole::Client));
        $this->assertTrue($professional->canActAsClient());
    }

    public function test_client_can_select_professional_role_and_create_profile(): void
    {
        $client = User::factory()->create();
        $headers = $this->authHeaders($client);

        $this->withHeaders($headers)
            ->putJson('/api/v1/me', [
                'role' => UserRole::Professional->value,
            ])
            ->assertOk()
            ->assertJsonPath('user.role', UserRole::Professional->value);

        $this->withHeaders($headers)
            ->postJson('/api/v1/professional-profile', [
                'bio' => 'Professional profile selected by the user.',
            ])
            ->assertCreated();
    }

    public function test_professional_cannot_demote_to_client(): void
    {
        $professional = User::factory()->professional()->create();

        $this->withHeaders($this->authHeaders($professional))
            ->putJson('/api/v1/me', [
                'role' => UserRole::Client->value,
            ])
            ->assertUnprocessable()
            ->assertJsonPath('error.type', 'ValidationError');
    }

    private function authHeaders(User $user): array
    {
        return [
            'Authorization' => 'Bearer '.auth('user_jwt')->login($user),
            'Accept' => 'application/json',
        ];
    }
}
