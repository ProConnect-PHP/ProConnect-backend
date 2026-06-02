<?php

namespace Tests\Feature\Booking;

use App\Enums\Booking\BookingStatus;
use App\Models\Availability\AvailabilityRule;
use App\Models\Booking\Booking;
use App\Models\Service\Service;
use App\Models\User\ProfessionalProfile;
use App\Models\User\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateBookingApiTest extends TestCase
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

    public function test_guest_cannot_create_booking(): void
    {
        $service = $this->createBookableService();

        $response = $this->postJson("/api/v1/services/{$service->id}/bookings", [
            'starts_at' => '2026-06-15 09:00:00',
        ]);

        $response
            ->assertUnauthorized()
            ->assertJsonPath('error.type', 'Unauthorized');
    }

    public function test_client_can_book_available_slot(): void
    {
        $client = User::factory()->create();
        $service = $this->createBookableService([
            'price' => 1500,
            'duration_minutes' => 60,
            'modality' => 'remota',
        ]);

        $response = $this
            ->withHeaders($this->authHeaders($client))
            ->postJson("/api/v1/services/{$service->id}/bookings", [
                'starts_at' => '2026-06-15 09:00:00',
            ]);

        $response
            ->assertCreated()
            ->assertJsonPath('message', 'Reserva creada correctamente')
            ->assertJsonPath('booking.service_id', $service->id)
            ->assertJsonPath('booking.client_id', $client->id)
            ->assertJsonPath('booking.status', BookingStatus::Pending->value)
            ->assertJsonPath('booking.starts_at', '2026-06-15 09:00:00')
            ->assertJsonPath('booking.ends_at', '2026-06-15 10:00:00')
            ->assertJsonPath('booking.modality', 'remota')
            ->assertJsonPath('booking.price_snapshot', '1500.00')
            ->assertJsonPath('booking.duration_minutes_snapshot', 60);

        $this->assertDatabaseHas('bookings', [
            'service_id' => $service->id,
            'client_id' => $client->id,
            'status' => BookingStatus::Pending->value,
        ]);
    }

    public function test_cannot_book_slot_that_does_not_exist(): void
    {
        $client = User::factory()->create();
        $service = $this->createBookableService();

        $response = $this
            ->withHeaders($this->authHeaders($client))
            ->postJson("/api/v1/services/{$service->id}/bookings", [
                'starts_at' => '2026-06-15 08:00:00',
            ]);

        $response
            ->assertUnprocessable()
            ->assertJsonPath('error.type', 'InvalidBookingSlot');
    }

    public function test_cannot_book_inactive_service(): void
    {
        $client = User::factory()->create();
        $service = $this->createBookableService([
            'is_active' => false,
        ]);

        $response = $this
            ->withHeaders($this->authHeaders($client))
            ->postJson("/api/v1/services/{$service->id}/bookings", [
                'starts_at' => '2026-06-15 09:00:00',
            ]);

        $response
            ->assertUnprocessable()
            ->assertJsonPath('error.type', 'ServiceNotAvailable');
    }

    public function test_professional_cannot_book_own_service(): void
    {
        [$professionalUser, $profile] = $this->createProfessional();
        $service = $this->createBookableService([
            'professional_id' => $profile->id,
        ]);

        $response = $this
            ->withHeaders($this->authHeaders($professionalUser))
            ->postJson("/api/v1/services/{$service->id}/bookings", [
                'starts_at' => '2026-06-15 09:00:00',
            ]);

        $response
            ->assertForbidden()
            ->assertJsonPath('error.type', 'CannotBookOwnService');
    }

    public function test_cannot_book_same_slot_twice(): void
    {
        $client = User::factory()->create();
        $otherClient = User::factory()->create();
        $service = $this->createBookableService();

        $this->createBooking($service, $otherClient, [
            'starts_at' => '2026-06-15 09:00:00',
            'ends_at' => '2026-06-15 10:00:00',
        ]);

        $response = $this
            ->withHeaders($this->authHeaders($client))
            ->postJson("/api/v1/services/{$service->id}/bookings", [
                'starts_at' => '2026-06-15 09:00:00',
            ]);

        $response
            ->assertConflict()
            ->assertJsonPath('error.type', 'BookingSlotAlreadyTaken');
    }

    public function test_cannot_book_overlapping_slot(): void
    {
        $client = User::factory()->create();
        $otherClient = User::factory()->create();
        $service = $this->createBookableService();

        $this->createBooking($service, $otherClient, [
            'starts_at' => '2026-06-15 09:30:00',
            'ends_at' => '2026-06-15 10:30:00',
        ]);

        $response = $this
            ->withHeaders($this->authHeaders($client))
            ->postJson("/api/v1/services/{$service->id}/bookings", [
                'starts_at' => '2026-06-15 09:00:00',
            ]);

        $response
            ->assertConflict()
            ->assertJsonPath('error.type', 'BookingSlotAlreadyTaken');
    }

    public function test_respects_max_bookings_per_client(): void
    {
        $client = User::factory()->create();
        $service = $this->createBookableService([
            'max_bookings_per_client' => 1,
        ]);

        $this->createBooking($service, $client, [
            'starts_at' => '2026-06-15 09:00:00',
            'ends_at' => '2026-06-15 10:00:00',
        ]);

        $response = $this
            ->withHeaders($this->authHeaders($client))
            ->postJson("/api/v1/services/{$service->id}/bookings", [
                'starts_at' => '2026-06-15 10:15:00',
            ]);

        $response
            ->assertConflict()
            ->assertJsonPath('error.type', 'MaxBookingsPerClientReached');
    }

    public function test_cancelled_booking_does_not_count_for_max_bookings_per_client(): void
    {
        $client = User::factory()->create();
        $service = $this->createBookableService([
            'max_bookings_per_client' => 1,
        ]);

        $this->createBooking($service, $client, [
            'starts_at' => '2026-06-15 09:00:00',
            'ends_at' => '2026-06-15 10:00:00',
            'status' => BookingStatus::Cancelled,
            'cancelled_at' => now(),
        ]);

        $response = $this
            ->withHeaders($this->authHeaders($client))
            ->postJson("/api/v1/services/{$service->id}/bookings", [
                'starts_at' => '2026-06-15 10:15:00',
            ]);

        $response
            ->assertCreated()
            ->assertJsonPath('booking.starts_at', '2026-06-15 10:15:00');
    }

    public function test_respects_service_date_window(): void
    {
        $client = User::factory()->create();
        $service = $this->createBookableService([
            'starts_at' => '2026-06-20',
        ]);

        $response = $this
            ->withHeaders($this->authHeaders($client))
            ->postJson("/api/v1/services/{$service->id}/bookings", [
                'starts_at' => '2026-06-15 09:00:00',
            ]);

        $response
            ->assertUnprocessable()
            ->assertJsonPath('error.type', 'ServiceNotAvailableOnDate');
    }

    private function createBookableService(array $overrides = []): Service
    {
        $service = Service::factory()->create([
            'duration_minutes' => 60,
            'buffer_minutes' => 15,
            'is_active' => true,
            ...$overrides,
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

    private function createBooking(Service $service, User $client, array $overrides = []): Booking
    {
        return Booking::factory()->create([
            'service_id' => $service->id,
            'professional_id' => $service->professional_id,
            'client_id' => $client->id,
            'starts_at' => '2026-06-15 09:00:00',
            'ends_at' => '2026-06-15 10:00:00',
            'status' => BookingStatus::Pending,
            'modality' => $service->modality,
            'price_snapshot' => $service->price,
            'duration_minutes_snapshot' => $service->duration_minutes,
            ...$overrides,
        ]);
    }

    private function createProfessional(): array
    {
        $user = User::factory()->professional()->create();
        $profile = ProfessionalProfile::factory()->create([
            'user_id' => $user->id,
        ]);

        return [$user, $profile];
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
