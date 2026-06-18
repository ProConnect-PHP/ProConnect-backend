<?php

namespace Tests\Feature\Admin;

use App\Models\User\ProfessionalProfile;
use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_admin_metrics(): void
    {
        $this->getJson('/api/v1/admin/metrics')
            ->assertUnauthorized()
            ->assertJsonPath('error.type', 'Unauthorized');
    }

    public function test_client_cannot_access_admin_metrics(): void
    {
        $client = User::factory()->create();

        $this->getJson('/api/v1/admin/metrics', $this->authHeaders($client))
            ->assertForbidden()
            ->assertJsonPath('error.type', 'Forbidden')
            ->assertJsonPath('error.message', 'This action is only available for administrators.');
    }

    public function test_professional_cannot_access_admin_metrics(): void
    {
        $professional = $this->createProfessional();

        $this->getJson('/api/v1/admin/metrics', $this->authHeaders($professional))
            ->assertForbidden()
            ->assertJsonPath('error.type', 'Forbidden');
    }

    public function test_admin_can_access_admin_metrics(): void
    {
        $admin = User::factory()->admin()->create();

        $this->getJson('/api/v1/admin/metrics', $this->authHeaders($admin))
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'users_total',
                    'clients_total',
                    'professionals_total',
                    'admins_total',
                    'bookings_total',
                    'bookings_today',
                    'services_total',
                    'reviews_total',
                    'packages_total',
                    'payments_total',
                ],
            ]);
    }

    public function test_client_cannot_access_admin_activity_logs(): void
    {
        $client = User::factory()->create();

        $this->getJson('/api/v1/admin/activity-logs', $this->authHeaders($client))
            ->assertForbidden();
    }

    public function test_professional_cannot_access_admin_activity_logs(): void
    {
        $professional = $this->createProfessional();

        $this->getJson('/api/v1/admin/activity-logs', $this->authHeaders($professional))
            ->assertForbidden();
    }

    public function test_admin_can_access_admin_activity_logs(): void
    {
        $admin = User::factory()->admin()->create();

        $this->getJson('/api/v1/admin/activity-logs', $this->authHeaders($admin))
            ->assertOk()
            ->assertJsonStructure([
                'data',
                'meta' => ['current_page', 'per_page', 'total', 'last_page'],
            ]);
    }

    public function test_admin_can_list_users(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->create(['email' => 'client-list@example.test']);

        $this->getJson('/api/v1/admin/users', $this->authHeaders($admin))
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'name', 'email', 'role', 'status', 'created_at'],
                ],
                'meta' => ['current_page', 'per_page', 'total', 'last_page'],
            ]);
    }

    public function test_admin_can_view_user_detail(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();

        $this->getJson("/api/v1/admin/users/{$user->id}", $this->authHeaders($admin))
            ->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.role', 'client');
    }

    public function test_admin_can_disable_client_user(): void
    {
        $admin = User::factory()->admin()->create();
        $client = User::factory()->create();

        $this->patchJson(
            "/api/v1/admin/users/{$client->id}/status",
            ['status' => 'disabled'],
            $this->authHeaders($admin),
        )
            ->assertOk()
            ->assertJsonPath('data.id', $client->id)
            ->assertJsonPath('data.status', 'disabled');

        $this->assertDatabaseHas('users', [
            'id' => $client->id,
            'status' => 'disabled',
        ]);
    }

    public function test_admin_cannot_disable_last_active_admin(): void
    {
        $admin = User::factory()->admin()->create();

        $this->patchJson(
            "/api/v1/admin/users/{$admin->id}/status",
            ['status' => 'disabled'],
            $this->authHeaders($admin),
        )
            ->assertConflict()
            ->assertJsonPath('error.type', 'LastActiveAdmin');
    }

    public function test_disabled_user_cannot_login(): void
    {
        $user = User::factory()->create([
            'email' => 'disabled-login@example.test',
            'password' => 'password',
            'status' => 'disabled',
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertUnauthorized();
    }

    public function test_client_cannot_promote_itself_to_admin(): void
    {
        $client = User::factory()->create();

        $this->putJson(
            '/api/v1/me',
            ['role' => 'admin'],
            $this->authHeaders($client),
        )
            ->assertUnprocessable()
            ->assertJsonPath('error.type', 'ValidationError');
    }

    private function createProfessional(): User
    {
        $professional = User::factory()->professional()->create();

        ProfessionalProfile::factory()->create([
            'user_id' => $professional->id,
        ]);

        return $professional;
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
