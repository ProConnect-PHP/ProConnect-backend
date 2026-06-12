<?php

namespace Tests\Feature\ActivityLog;

use App\Models\Availability\AvailabilityRule;
use App\Models\Logs\ActivityLog;
use App\Models\Service\Service;
use App\Models\User\ProfessionalProfile;
use App\Models\User\User;
use App\Support\ActivityLog\ActivityLogActorMode;
use App\Support\ActivityLog\ActivityLogEvent;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;

class ActivityLogActorModeTest extends TestCase
{
    use DatabaseMigrations;
    use InteractsWithActivityLogs;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-06-01 12:00:00');
        $this->clearActivityLogs();
    }

    protected function tearDown(): void
    {
        $this->clearActivityLogs();
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_professional_booking_as_client_logs_acting_as_client(): void
    {
        $professional = User::factory()->professional()->create();
        ProfessionalProfile::factory()->create([
            'user_id' => $professional->id,
        ]);

        $serviceOwner = ProfessionalProfile::factory()->create();
        $service = Service::factory()->create([
            'professional_id' => $serviceOwner->id,
            'duration_minutes' => 60,
            'buffer_minutes' => 15,
            'is_active' => true,
            'modality' => 'remota',
        ]);
        AvailabilityRule::factory()->create([
            'service_id' => $service->id,
            'day_of_week' => 1,
            'start_time' => '09:00',
            'end_time' => '12:00',
            'is_active' => true,
        ]);

        $this
            ->withHeaders($this->authHeaders($professional))
            ->postJson("/api/v1/services/{$service->id}/bookings", [
                'starts_at' => '2026-06-15 09:00:00',
            ])
            ->assertCreated();

        $log = $this->activityLog(ActivityLogEvent::BookingCreated->value);

        $this->assertNotNull($log);
        $this->assertSame($professional->id, $log->actor_id);
        $this->assertSame('professional', $log->actor_role);
        $this->assertSame(ActivityLogActorMode::Client->value, $log->acting_as);
        $this->assertSame($serviceOwner->id, $log->entity_owner_id);
    }

    public function test_professional_service_creation_logs_acting_as_professional(): void
    {
        $professional = User::factory()->professional()->create();
        $profile = ProfessionalProfile::factory()->create([
            'user_id' => $professional->id,
        ]);

        $response = $this
            ->withHeaders($this->authHeaders($professional))
            ->postJson('/api/v1/services', $this->servicePayload())
            ->assertCreated();

        $log = $this->activityLog(ActivityLogEvent::ServiceCreated->value);

        $this->assertNotNull($log);
        $this->assertSame($response->json('service.id'), $log->entity_id);
        $this->assertSame($professional->id, $log->actor_id);
        $this->assertSame('professional', $log->actor_role);
        $this->assertSame(ActivityLogActorMode::Professional->value, $log->acting_as);
        $this->assertSame($profile->id, $log->entity_owner_id);
    }

    public function test_failed_login_logs_acting_as_guest(): void
    {
        User::factory()->create([
            'email' => 'actor-mode-failed-login@example.test',
            'password' => 'password',
        ]);

        $this
            ->withServerVariables(['REMOTE_ADDR' => '198.51.100.73'])
            ->postJson('/api/v1/auth/login', [
                'email' => 'actor-mode-failed-login@example.test',
                'password' => 'invalid-password',
            ])
            ->assertUnauthorized();

        $log = $this->activityLog(ActivityLogEvent::AuthLoginFailed->value);

        $this->assertNotNull($log);
        $this->assertSame('guest', $log->actor_type);
        $this->assertNull($log->actor_role);
        $this->assertSame(ActivityLogActorMode::Guest->value, $log->acting_as);
    }

    public function test_admin_activity_log_filter_supports_acting_as(): void
    {
        $admin = User::factory()->admin()->create();

        ActivityLog::query()->create([
            'event' => 'booking.created',
            'severity' => 'info',
            'acting_as' => ActivityLogActorMode::Client->value,
            'entity_type' => 'booking',
            'entity_id' => 'client-mode-booking',
            'metadata' => [],
            'created_at' => now(),
        ]);
        ActivityLog::query()->create([
            'event' => 'service.created',
            'severity' => 'info',
            'acting_as' => ActivityLogActorMode::Professional->value,
            'entity_type' => 'service',
            'entity_id' => 'professional-mode-service',
            'metadata' => [],
            'created_at' => now(),
        ]);

        $response = $this
            ->withHeaders($this->authHeaders($admin))
            ->getJson('/api/v1/admin/activity-logs?acting_as=client&limit=10');

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.acting_as', ActivityLogActorMode::Client->value)
            ->assertJsonPath('data.0.entity_id', 'client-mode-booking');
    }

    private function authHeaders(User $user): array
    {
        return [
            'Authorization' => 'Bearer '.auth('user_jwt')->login($user),
            'Accept' => 'application/json',
        ];
    }

    private function servicePayload(): array
    {
        return [
            'name' => 'Actor mode consultation',
            'description' => 'Service used to verify activity log actor mode.',
            'price' => 150,
            'duration_minutes' => 60,
            'modality' => 'remota',
            'address' => null,
            'link' => 'https://example.test/meeting',
            'latitude' => null,
            'longitude' => null,
            'max_bookings_per_client' => null,
            'min_reschedule_minutes' => 30,
            'buffer_minutes' => 0,
            'starts_at' => null,
            'ends_at' => null,
            'is_active' => true,
        ];
    }
}
