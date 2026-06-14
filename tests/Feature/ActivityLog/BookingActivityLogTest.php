<?php

namespace Tests\Feature\ActivityLog;

use App\Enums\Booking\BookingStatus;
use App\Models\Availability\AvailabilityRule;
use App\Models\Booking\Booking;
use App\Models\Service\Service;
use App\Models\User\User;
use App\Support\ActivityLog\ActivityLogEvent;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;

class BookingActivityLogTest extends TestCase
{
    use DatabaseMigrations;
    use InteractsWithActivityLogs;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-06-01 12:00:00');
        $this->useSynchronousActivityLogQueue();
        $this->clearActivityLogs();
    }

    protected function tearDown(): void
    {
        $this->clearActivityLogs();
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_booking_creation_creates_activity_log(): void
    {
        $client = User::factory()->create();
        $service = Service::factory()->create([
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

        $response = $this
            ->withHeaders($this->authHeaders($client))
            ->postJson("/api/v1/services/{$service->id}/bookings", [
                'starts_at' => '2026-06-15 09:00:00',
            ])
            ->assertCreated();

        $log = $this->activityLog(ActivityLogEvent::BookingCreated->value);

        $this->assertNotNull($log);
        $this->assertSame($response->json('booking.id'), $log->entity_id);
        $this->assertSame($client->id, $log->metadata['client_id']);
        $this->assertFalse($log->metadata['used_package']);
    }

    public function test_booking_cancellation_creates_activity_log(): void
    {
        $client = User::factory()->create();
        $booking = Booking::factory()->create([
            'client_id' => $client->id,
            'status' => BookingStatus::Pending,
            'starts_at' => '2026-06-15 09:00:00',
            'ends_at' => '2026-06-15 10:00:00',
        ]);

        $this
            ->withHeaders($this->authHeaders($client))
            ->postJson("/api/v1/bookings/{$booking->id}/cancel", [
                'reason' => 'Cambio de agenda',
            ])
            ->assertOk();

        $log = $this->activityLog(ActivityLogEvent::BookingCancelled->value);

        $this->assertNotNull($log);
        $this->assertSame($booking->id, $log->entity_id);
        $this->assertSame('pending', $log->metadata['previous_status']);
        $this->assertSame('cancelled', $log->metadata['new_status']);
        $this->assertSame('Cambio de agenda', $log->metadata['reason']);
    }

    private function authHeaders(User $user): array
    {
        return [
            'Authorization' => 'Bearer '.auth('user_jwt')->login($user),
            'Accept' => 'application/json',
        ];
    }
}
