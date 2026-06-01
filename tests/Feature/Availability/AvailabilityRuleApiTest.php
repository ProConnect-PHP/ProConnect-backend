<?php

namespace Tests\Feature\Availability;

use App\Models\Availability\AvailabilityRule;
use App\Models\Service\Service;
use App\Models\User\ProfessionalProfile;
use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AvailabilityRuleApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_user_cannot_list_rules(): void
    {
        [, $profile] = $this->createProfessionalUser();
        $service = $this->createServiceForProfile($profile);

        $response = $this->getJson("/api/v1/services/{$service->id}/availability-rules");

        $response
            ->assertUnauthorized()
            ->assertJsonPath('error.type', 'Unauthorized');
    }

    public function test_owner_can_create_rule(): void
    {
        [$user, $profile] = $this->createProfessionalUser();
        $service = $this->createServiceForProfile($profile);

        $response = $this
            ->withHeaders($this->authHeaders($user))
            ->postJson("/api/v1/services/{$service->id}/availability-rules", [
                'day_of_week' => 1,
                'start_time' => '09:00',
                'end_time' => '17:00',
                'is_active' => true,
            ]);

        $response
            ->assertCreated()
            ->assertJsonPath('availability_rule.service_id', $service->id)
            ->assertJsonPath('availability_rule.day_of_week', 1)
            ->assertJsonPath('availability_rule.start_time', '09:00')
            ->assertJsonPath('availability_rule.end_time', '17:00');

        $this->assertDatabaseHas('availability_rules', [
            'service_id' => $service->id,
            'day_of_week' => 1,
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
        ]);
    }

    public function test_owner_can_list_rules(): void
    {
        [$user, $profile] = $this->createProfessionalUser();
        $service = $this->createServiceForProfile($profile);

        AvailabilityRule::factory()->create([
            'service_id' => $service->id,
            'day_of_week' => 1,
        ]);
        AvailabilityRule::factory()->create([
            'service_id' => $service->id,
            'day_of_week' => 2,
        ]);

        $response = $this
            ->withHeaders($this->authHeaders($user))
            ->getJson("/api/v1/services/{$service->id}/availability-rules");

        $response
            ->assertOk()
            ->assertJsonCount(2, 'availability_rules');
    }

    public function test_owner_can_update_rule(): void
    {
        [$user, $profile] = $this->createProfessionalUser();
        $service = $this->createServiceForProfile($profile);
        $rule = AvailabilityRule::factory()->create([
            'service_id' => $service->id,
        ]);

        $response = $this
            ->withHeaders($this->authHeaders($user))
            ->putJson("/api/v1/availability-rules/{$rule->id}", [
                'start_time' => '10:00',
                'end_time' => '16:00',
                'is_active' => false,
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('availability_rule.id', $rule->id)
            ->assertJsonPath('availability_rule.start_time', '10:00:00')
            ->assertJsonPath('availability_rule.end_time', '16:00:00')
            ->assertJsonPath('availability_rule.is_active', false);

        $this->assertDatabaseHas('availability_rules', [
            'id' => $rule->id,
            'start_time' => '10:00:00',
            'end_time' => '16:00:00',
            'is_active' => false,
        ]);
    }

    public function test_owner_can_delete_rule(): void
    {
        [$user, $profile] = $this->createProfessionalUser();
        $service = $this->createServiceForProfile($profile);
        $rule = AvailabilityRule::factory()->create([
            'service_id' => $service->id,
        ]);

        $response = $this
            ->withHeaders($this->authHeaders($user))
            ->deleteJson("/api/v1/availability-rules/{$rule->id}");

        $response
            ->assertOk()
            ->assertJsonStructure(['message']);

        $this->assertSoftDeleted('availability_rules', [
            'id' => $rule->id,
        ]);
    }

    public function test_non_owner_cannot_create_rule_for_foreign_service(): void
    {
        [, $ownerProfile] = $this->createProfessionalUser();
        [$otherUser] = $this->createProfessionalUser();
        $service = $this->createServiceForProfile($ownerProfile);

        $response = $this
            ->withHeaders($this->authHeaders($otherUser))
            ->postJson("/api/v1/services/{$service->id}/availability-rules", $this->rulePayload());

        $response
            ->assertForbidden()
            ->assertJsonPath('error.type', 'Forbidden');
    }

    public function test_non_owner_cannot_list_rules_for_foreign_service(): void
    {
        [, $ownerProfile] = $this->createProfessionalUser();
        [$otherUser] = $this->createProfessionalUser();
        $service = $this->createServiceForProfile($ownerProfile);

        $response = $this
            ->withHeaders($this->authHeaders($otherUser))
            ->getJson("/api/v1/services/{$service->id}/availability-rules");

        $response
            ->assertForbidden()
            ->assertJsonPath('error.type', 'Forbidden');
    }

    public function test_non_owner_cannot_update_foreign_rule(): void
    {
        [, $ownerProfile] = $this->createProfessionalUser();
        [$otherUser] = $this->createProfessionalUser();
        $service = $this->createServiceForProfile($ownerProfile);
        $rule = AvailabilityRule::factory()->create([
            'service_id' => $service->id,
        ]);

        $response = $this
            ->withHeaders($this->authHeaders($otherUser))
            ->putJson("/api/v1/availability-rules/{$rule->id}", [
                'start_time' => '10:00',
                'end_time' => '16:00',
            ]);

        $response
            ->assertForbidden()
            ->assertJsonPath('error.type', 'Forbidden');
    }

    public function test_non_owner_cannot_delete_foreign_rule(): void
    {
        [, $ownerProfile] = $this->createProfessionalUser();
        [$otherUser] = $this->createProfessionalUser();
        $service = $this->createServiceForProfile($ownerProfile);
        $rule = AvailabilityRule::factory()->create([
            'service_id' => $service->id,
        ]);

        $response = $this
            ->withHeaders($this->authHeaders($otherUser))
            ->deleteJson("/api/v1/availability-rules/{$rule->id}");

        $response
            ->assertForbidden()
            ->assertJsonPath('error.type', 'Forbidden');

        $this->assertNotSoftDeleted('availability_rules', [
            'id' => $rule->id,
        ]);
    }

    public function test_validation_fails_with_invalid_day_of_week(): void
    {
        [$user, $profile] = $this->createProfessionalUser();
        $service = $this->createServiceForProfile($profile);

        $response = $this
            ->withHeaders($this->authHeaders($user))
            ->postJson("/api/v1/services/{$service->id}/availability-rules", $this->rulePayload([
                'day_of_week' => 9,
            ]));

        $response
            ->assertUnprocessable()
            ->assertJsonPath('error.type', 'ValidationError')
            ->assertJsonStructure([
                'error' => [
                    'details' => ['day_of_week'],
                ],
            ]);
    }

    public function test_validation_fails_when_end_time_is_before_start_time(): void
    {
        [$user, $profile] = $this->createProfessionalUser();
        $service = $this->createServiceForProfile($profile);

        $response = $this
            ->withHeaders($this->authHeaders($user))
            ->postJson("/api/v1/services/{$service->id}/availability-rules", $this->rulePayload([
                'start_time' => '17:00',
                'end_time' => '09:00',
            ]));

        $response
            ->assertUnprocessable()
            ->assertJsonPath('error.type', 'ValidationError')
            ->assertJsonStructure([
                'error' => [
                    'details' => ['end_time'],
                ],
            ]);
    }

    private function authHeaders(User $user): array
    {
        $token = auth('user_jwt')->login($user);

        return [
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ];
    }

    private function createProfessionalUser(): array
    {
        $user = User::factory()->professional()->create();
        $profile = ProfessionalProfile::factory()->create([
            'user_id' => $user->id,
        ]);

        return [$user, $profile];
    }

    private function createServiceForProfile(ProfessionalProfile $profile, array $overrides = []): Service
    {
        return Service::factory()->create([
            'professional_id' => $profile->id,
            ...$overrides,
        ]);
    }

    private function rulePayload(array $overrides = []): array
    {
        return array_merge([
            'day_of_week' => 1,
            'start_time' => '09:00',
            'end_time' => '17:00',
            'is_active' => true,
        ], $overrides);
    }
}
