<?php

namespace Tests\Feature\Availability;

use App\Models\Availability\AvailabilityException;
use App\Models\Availability\AvailabilityRule;
use App\Models\Service\Service;
use App\Models\User\ProfessionalProfile;
use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AvailabilitySlotsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_slots_for_day_with_active_rule(): void
    {
        [, $profile] = $this->createProfessionalUser();
        $service = $this->createServiceForProfile($profile, [
            'duration_minutes' => 60,
            'buffer_minutes' => 15,
        ]);

        AvailabilityRule::factory()->create([
            'service_id' => $service->id,
            'day_of_week' => 1,
            'start_time' => '09:00',
            'end_time' => '12:00',
            'is_active' => true,
        ]);

        $response = $this->getJson("/api/v1/services/{$service->id}/availability?date=2026-06-15");

        $response
            ->assertOk()
            ->assertJsonPath('service_id', $service->id)
            ->assertJsonPath('date', '2026-06-15')
            ->assertJsonCount(2, 'slots')
            ->assertJsonPath('slots.0.starts_at', '2026-06-15 09:00:00')
            ->assertJsonPath('slots.0.ends_at', '2026-06-15 10:00:00')
            ->assertJsonPath('slots.1.starts_at', '2026-06-15 10:15:00')
            ->assertJsonPath('slots.1.ends_at', '2026-06-15 11:15:00');
    }

    public function test_returns_empty_slots_without_rule_for_requested_day(): void
    {
        [, $profile] = $this->createProfessionalUser();
        $service = $this->createServiceForProfile($profile);

        AvailabilityRule::factory()->create([
            'service_id' => $service->id,
            'day_of_week' => 1,
        ]);

        $response = $this->getJson("/api/v1/services/{$service->id}/availability?date=2026-06-14");

        $response
            ->assertOk()
            ->assertJsonPath('slots', []);
    }

    public function test_returns_empty_slots_when_rule_is_inactive(): void
    {
        [, $profile] = $this->createProfessionalUser();
        $service = $this->createServiceForProfile($profile);

        AvailabilityRule::factory()->create([
            'service_id' => $service->id,
            'day_of_week' => 1,
            'is_active' => false,
        ]);

        $response = $this->getJson("/api/v1/services/{$service->id}/availability?date=2026-06-15");

        $response
            ->assertOk()
            ->assertJsonPath('slots', []);
    }

    public function test_unavailable_exception_blocks_the_day(): void
    {
        [, $profile] = $this->createProfessionalUser();
        $service = $this->createServiceForProfile($profile);

        AvailabilityRule::factory()->create([
            'service_id' => $service->id,
            'day_of_week' => 1,
            'start_time' => '09:00',
            'end_time' => '17:00',
        ]);
        AvailabilityException::factory()->create([
            'service_id' => $service->id,
            'exception_date' => '2026-06-15',
            'is_unavailable' => true,
        ]);

        $response = $this->getJson("/api/v1/services/{$service->id}/availability?date=2026-06-15");

        $response
            ->assertOk()
            ->assertJsonPath('slots', []);
    }

    public function test_alternative_hours_exception_replaces_normal_window(): void
    {
        [, $profile] = $this->createProfessionalUser();
        $service = $this->createServiceForProfile($profile, [
            'duration_minutes' => 60,
            'buffer_minutes' => 15,
        ]);

        AvailabilityRule::factory()->create([
            'service_id' => $service->id,
            'day_of_week' => 1,
            'start_time' => '09:00',
            'end_time' => '17:00',
        ]);
        AvailabilityException::factory()->create([
            'service_id' => $service->id,
            'exception_date' => '2026-06-15',
            'is_unavailable' => false,
            'alt_start' => '11:00',
            'alt_end' => '14:00',
        ]);

        $response = $this->getJson("/api/v1/services/{$service->id}/availability?date=2026-06-15");

        $response
            ->assertOk()
            ->assertJsonCount(2, 'slots')
            ->assertJsonPath('slots.0.starts_at', '2026-06-15 11:00:00')
            ->assertJsonPath('slots.0.ends_at', '2026-06-15 12:00:00')
            ->assertJsonPath('slots.1.starts_at', '2026-06-15 12:15:00')
            ->assertJsonPath('slots.1.ends_at', '2026-06-15 13:15:00');
    }

    public function test_date_query_is_required(): void
    {
        [, $profile] = $this->createProfessionalUser();
        $service = $this->createServiceForProfile($profile);

        $response = $this->getJson("/api/v1/services/{$service->id}/availability");

        $response
            ->assertUnprocessable()
            ->assertJsonPath('error.type', 'ValidationError')
            ->assertJsonStructure([
                'error' => [
                    'details' => ['date'],
                ],
            ]);
    }

    public function test_invalid_date_query_returns_422(): void
    {
        [, $profile] = $this->createProfessionalUser();
        $service = $this->createServiceForProfile($profile);

        $response = $this->getJson("/api/v1/services/{$service->id}/availability?date=not-a-date");

        $response
            ->assertUnprocessable()
            ->assertJsonPath('error.type', 'ValidationError')
            ->assertJsonStructure([
                'error' => [
                    'details' => ['date'],
                ],
            ]);
    }

    public function test_missing_service_returns_404_json(): void
    {
        $missingServiceId = (string) Str::uuid();

        $response = $this->getJson("/api/v1/services/{$missingServiceId}/availability?date=2026-06-15");

        $response
            ->assertNotFound()
            ->assertJsonPath('error.type', 'NotFound');
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
}
