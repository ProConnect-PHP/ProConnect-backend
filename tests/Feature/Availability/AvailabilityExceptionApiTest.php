<?php

namespace Tests\Feature\Availability;

use App\Models\Availability\AvailabilityException;
use App\Models\Service\Service;
use App\Models\User\ProfessionalProfile;
use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AvailabilityExceptionApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_user_cannot_list_exceptions(): void
    {
        [, $profile] = $this->createProfessionalUser();
        $service = $this->createServiceForProfile($profile);

        $response = $this->getJson("/api/v1/services/{$service->id}/availability-exceptions");

        $response
            ->assertUnauthorized()
            ->assertJsonPath('error.type', 'Unauthorized');
    }

    public function test_owner_can_create_unavailable_exception(): void
    {
        [$user, $profile] = $this->createProfessionalUser();
        $service = $this->createServiceForProfile($profile);

        $response = $this
            ->withHeaders($this->authHeaders($user))
            ->postJson("/api/v1/services/{$service->id}/availability-exceptions", [
                'exception_date' => '2026-06-15',
                'is_unavailable' => true,
                'reason' => 'Feriado',
            ]);

        $response
            ->assertCreated()
            ->assertJsonPath('availability_exception.service_id', $service->id)
            ->assertJsonPath('availability_exception.exception_date', '2026-06-15')
            ->assertJsonPath('availability_exception.is_unavailable', true)
            ->assertJsonPath('availability_exception.reason', 'Feriado');

        $this->assertDatabaseHas('availability_exceptions', [
            'service_id' => $service->id,
            'exception_date' => '2026-06-15',
            'is_unavailable' => true,
            'reason' => 'Feriado',
        ]);
    }

    public function test_owner_can_create_exception_with_alternative_hours(): void
    {
        [$user, $profile] = $this->createProfessionalUser();
        $service = $this->createServiceForProfile($profile);

        $response = $this
            ->withHeaders($this->authHeaders($user))
            ->postJson("/api/v1/services/{$service->id}/availability-exceptions", [
                'exception_date' => '2026-06-16',
                'is_unavailable' => false,
                'alt_start' => '11:00',
                'alt_end' => '14:00',
                'reason' => 'Horario reducido',
            ]);

        $response
            ->assertCreated()
            ->assertJsonPath('availability_exception.alt_start', '11:00')
            ->assertJsonPath('availability_exception.alt_end', '14:00');

        $this->assertDatabaseHas('availability_exceptions', [
            'service_id' => $service->id,
            'exception_date' => '2026-06-16',
            'is_unavailable' => false,
            'alt_start' => '11:00:00',
            'alt_end' => '14:00:00',
        ]);
    }

    public function test_owner_can_list_exceptions(): void
    {
        [$user, $profile] = $this->createProfessionalUser();
        $service = $this->createServiceForProfile($profile);

        AvailabilityException::factory()->create([
            'service_id' => $service->id,
            'exception_date' => '2026-06-15',
        ]);
        AvailabilityException::factory()->create([
            'service_id' => $service->id,
            'exception_date' => '2026-06-16',
        ]);

        $response = $this
            ->withHeaders($this->authHeaders($user))
            ->getJson("/api/v1/services/{$service->id}/availability-exceptions");

        $response
            ->assertOk()
            ->assertJsonCount(2, 'availability_exceptions');
    }

    public function test_owner_can_update_exception(): void
    {
        [$user, $profile] = $this->createProfessionalUser();
        $service = $this->createServiceForProfile($profile);
        $exception = AvailabilityException::factory()->create([
            'service_id' => $service->id,
            'exception_date' => '2026-06-15',
            'is_unavailable' => true,
        ]);

        $response = $this
            ->withHeaders($this->authHeaders($user))
            ->putJson("/api/v1/availability-exceptions/{$exception->id}", [
                'is_unavailable' => false,
                'alt_start' => '10:00',
                'alt_end' => '13:00',
                'reason' => 'Horario especial',
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('availability_exception.id', $exception->id)
            ->assertJsonPath('availability_exception.is_unavailable', false)
            ->assertJsonPath('availability_exception.alt_start', '10:00:00')
            ->assertJsonPath('availability_exception.alt_end', '13:00:00')
            ->assertJsonPath('availability_exception.reason', 'Horario especial');

        $this->assertDatabaseHas('availability_exceptions', [
            'id' => $exception->id,
            'is_unavailable' => false,
            'alt_start' => '10:00:00',
            'alt_end' => '13:00:00',
            'reason' => 'Horario especial',
        ]);
    }

    public function test_owner_can_delete_exception(): void
    {
        [$user, $profile] = $this->createProfessionalUser();
        $service = $this->createServiceForProfile($profile);
        $exception = AvailabilityException::factory()->create([
            'service_id' => $service->id,
        ]);

        $response = $this
            ->withHeaders($this->authHeaders($user))
            ->deleteJson("/api/v1/availability-exceptions/{$exception->id}");

        $response
            ->assertOk()
            ->assertJsonStructure(['message']);

        $this->assertSoftDeleted('availability_exceptions', [
            'id' => $exception->id,
        ]);
    }

    public function test_non_owner_cannot_create_exception_for_foreign_service(): void
    {
        [, $ownerProfile] = $this->createProfessionalUser();
        [$otherUser] = $this->createProfessionalUser();
        $service = $this->createServiceForProfile($ownerProfile);

        $response = $this
            ->withHeaders($this->authHeaders($otherUser))
            ->postJson("/api/v1/services/{$service->id}/availability-exceptions", $this->exceptionPayload());

        $response
            ->assertForbidden()
            ->assertJsonPath('error.type', 'Forbidden');
    }

    public function test_non_owner_cannot_list_exceptions_for_foreign_service(): void
    {
        [, $ownerProfile] = $this->createProfessionalUser();
        [$otherUser] = $this->createProfessionalUser();
        $service = $this->createServiceForProfile($ownerProfile);

        $response = $this
            ->withHeaders($this->authHeaders($otherUser))
            ->getJson("/api/v1/services/{$service->id}/availability-exceptions");

        $response
            ->assertForbidden()
            ->assertJsonPath('error.type', 'Forbidden');
    }

    public function test_non_owner_cannot_update_foreign_exception(): void
    {
        [, $ownerProfile] = $this->createProfessionalUser();
        [$otherUser] = $this->createProfessionalUser();
        $service = $this->createServiceForProfile($ownerProfile);
        $exception = AvailabilityException::factory()->create([
            'service_id' => $service->id,
        ]);

        $response = $this
            ->withHeaders($this->authHeaders($otherUser))
            ->putJson("/api/v1/availability-exceptions/{$exception->id}", [
                'reason' => 'Should fail',
            ]);

        $response
            ->assertForbidden()
            ->assertJsonPath('error.type', 'Forbidden');
    }

    public function test_non_owner_cannot_delete_foreign_exception(): void
    {
        [, $ownerProfile] = $this->createProfessionalUser();
        [$otherUser] = $this->createProfessionalUser();
        $service = $this->createServiceForProfile($ownerProfile);
        $exception = AvailabilityException::factory()->create([
            'service_id' => $service->id,
        ]);

        $response = $this
            ->withHeaders($this->authHeaders($otherUser))
            ->deleteJson("/api/v1/availability-exceptions/{$exception->id}");

        $response
            ->assertForbidden()
            ->assertJsonPath('error.type', 'Forbidden');

        $this->assertNotSoftDeleted('availability_exceptions', [
            'id' => $exception->id,
        ]);
    }

    public function test_validation_fails_when_alt_end_is_before_alt_start(): void
    {
        [$user, $profile] = $this->createProfessionalUser();
        $service = $this->createServiceForProfile($profile);

        $response = $this
            ->withHeaders($this->authHeaders($user))
            ->postJson("/api/v1/services/{$service->id}/availability-exceptions", $this->exceptionPayload([
                'alt_start' => '14:00',
                'alt_end' => '11:00',
                'is_unavailable' => false,
            ]));

        $response
            ->assertUnprocessable()
            ->assertJsonPath('error.type', 'ValidationError')
            ->assertJsonStructure([
                'error' => [
                    'details' => ['alt_end'],
                ],
            ]);
    }

    public function test_validation_fails_without_exception_date(): void
    {
        [$user, $profile] = $this->createProfessionalUser();
        $service = $this->createServiceForProfile($profile);
        $payload = $this->exceptionPayload();
        unset($payload['exception_date']);

        $response = $this
            ->withHeaders($this->authHeaders($user))
            ->postJson("/api/v1/services/{$service->id}/availability-exceptions", $payload);

        $response
            ->assertUnprocessable()
            ->assertJsonPath('error.type', 'ValidationError')
            ->assertJsonStructure([
                'error' => [
                    'details' => ['exception_date'],
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

    private function exceptionPayload(array $overrides = []): array
    {
        return array_merge([
            'exception_date' => '2026-06-15',
            'is_unavailable' => true,
            'alt_start' => null,
            'alt_end' => null,
            'reason' => 'Feriado',
        ], $overrides);
    }
}
