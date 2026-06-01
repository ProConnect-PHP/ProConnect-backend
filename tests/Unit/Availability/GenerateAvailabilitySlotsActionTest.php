<?php

namespace Tests\Unit\Availability;

use App\Actions\Availability\GenerateAvailabilitySlotsAction;
use App\Models\Availability\AvailabilityException;
use App\Models\Availability\AvailabilityRule;
use App\Models\Service\Service;
use App\Models\User\ProfessionalProfile;
use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GenerateAvailabilitySlotsActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_generates_slots_with_active_rule(): void
    {
        $service = $this->createService([
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

        $slots = app(GenerateAvailabilitySlotsAction::class)($service, '2026-06-15');

        $this->assertCount(2, $slots);
        $this->assertSame('2026-06-15 09:00:00', $slots[0]['starts_at']);
        $this->assertSame('2026-06-15 10:15:00', $slots[1]['starts_at']);
    }

    public function test_does_not_generate_slots_without_rule(): void
    {
        $service = $this->createService();

        $slots = app(GenerateAvailabilitySlotsAction::class)($service, '2026-06-15');

        $this->assertSame([], $slots);
    }

    public function test_does_not_generate_slots_with_unavailable_exception(): void
    {
        $service = $this->createService();

        AvailabilityRule::factory()->create([
            'service_id' => $service->id,
            'day_of_week' => 1,
        ]);
        AvailabilityException::factory()->create([
            'service_id' => $service->id,
            'exception_date' => '2026-06-15',
            'is_unavailable' => true,
        ]);

        $slots = app(GenerateAvailabilitySlotsAction::class)($service, '2026-06-15');

        $this->assertSame([], $slots);
    }

    public function test_uses_alternative_exception_hours(): void
    {
        $service = $this->createService([
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

        $slots = app(GenerateAvailabilitySlotsAction::class)($service, '2026-06-15');

        $this->assertCount(2, $slots);
        $this->assertSame('2026-06-15 11:00:00', $slots[0]['starts_at']);
        $this->assertSame('2026-06-15 13:15:00', $slots[1]['ends_at']);
    }

    public function test_respects_service_duration(): void
    {
        $service = $this->createService([
            'duration_minutes' => 90,
            'buffer_minutes' => 0,
        ]);

        AvailabilityRule::factory()->create([
            'service_id' => $service->id,
            'day_of_week' => 1,
            'start_time' => '09:00',
            'end_time' => '12:00',
        ]);

        $slots = app(GenerateAvailabilitySlotsAction::class)($service, '2026-06-15');

        $this->assertSame([
            [
                'starts_at' => '2026-06-15 09:00:00',
                'ends_at' => '2026-06-15 10:30:00',
            ],
            [
                'starts_at' => '2026-06-15 10:30:00',
                'ends_at' => '2026-06-15 12:00:00',
            ],
        ], $slots);
    }

    public function test_respects_buffer(): void
    {
        $service = $this->createService([
            'duration_minutes' => 30,
            'buffer_minutes' => 15,
        ]);

        AvailabilityRule::factory()->create([
            'service_id' => $service->id,
            'day_of_week' => 1,
            'start_time' => '09:00',
            'end_time' => '10:30',
        ]);

        $slots = app(GenerateAvailabilitySlotsAction::class)($service, '2026-06-15');

        $this->assertSame([
            [
                'starts_at' => '2026-06-15 09:00:00',
                'ends_at' => '2026-06-15 09:30:00',
            ],
            [
                'starts_at' => '2026-06-15 09:45:00',
                'ends_at' => '2026-06-15 10:15:00',
            ],
        ], $slots);
    }

    private function createService(array $overrides = []): Service
    {
        $user = User::factory()->professional()->create();
        $profile = ProfessionalProfile::factory()->create([
            'user_id' => $user->id,
        ]);

        return Service::factory()->create([
            'professional_id' => $profile->id,
            ...$overrides,
        ]);
    }
}
