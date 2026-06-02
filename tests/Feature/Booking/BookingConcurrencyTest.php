<?php

namespace Tests\Feature\Booking;

use App\Models\Availability\AvailabilityRule;
use App\Models\Service\Service;
use App\Models\User\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-06-01 12:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_two_requests_to_same_slot_leave_only_one_booking(): void
    {
        $firstClient = User::factory()->create();
        $secondClient = User::factory()->create();
        $service = $this->createBookableService();

        $this
            ->withHeaders($this->authHeaders($firstClient))
            ->postJson("/api/v1/services/{$service->id}/bookings", [
                'starts_at' => '2026-06-15 09:00:00',
            ])
            ->assertCreated();

        $this
            ->withHeaders($this->authHeaders($secondClient))
            ->postJson("/api/v1/services/{$service->id}/bookings", [
                'starts_at' => '2026-06-15 09:00:00',
            ])
            ->assertConflict()
            ->assertJsonPath('error.type', 'BookingSlotAlreadyTaken');

        $this->assertDatabaseCount('bookings', 1);
    }

    private function createBookableService(): Service
    {
        $service = Service::factory()->create([
            'duration_minutes' => 60,
            'buffer_minutes' => 15,
            'is_active' => true,
        ]);

        AvailabilityRule::factory()->create([
            'service_id' => $service->id,
            'day_of_week' => 1,
            'start_time' => '09:00',
            'end_time' => '12:00',
            'is_active' => true,
        ]);

        return $service;
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
