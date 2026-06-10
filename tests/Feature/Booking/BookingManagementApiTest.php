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

class BookingManagementApiTest extends TestCase
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

    public function test_client_can_list_only_their_bookings(): void
    {
        $client = User::factory()->create();
        $otherClient = User::factory()->create();
        $service = $this->createBookableService();
        $myBooking = $this->createBooking($service, $client);
        $otherBooking = $this->createBooking($service, $otherClient, [
            'starts_at' => '2026-06-15 10:15:00',
            'ends_at' => '2026-06-15 11:15:00',
        ]);

        $response = $this
            ->withHeaders($this->authHeaders($client))
            ->getJson('/api/v1/bookings/my');

        $response
            ->assertOk()
            ->assertJsonCount(1, 'bookings')
            ->assertJsonPath('bookings.0.id', $myBooking->id);

        $this->assertNotSame($otherBooking->id, $response->json('bookings.0.id'));
    }

    public function test_professional_can_list_bookings_for_their_services(): void
    {
        [$professionalUser, $profile] = $this->createProfessional();
        [, $otherProfile] = $this->createProfessional();
        $client = User::factory()->create();
        $service = $this->createBookableService([
            'professional_id' => $profile->id,
        ]);
        $otherService = $this->createBookableService([
            'professional_id' => $otherProfile->id,
        ]);
        $booking = $this->createBooking($service, $client);
        $otherBooking = $this->createBooking($otherService, $client);

        $response = $this
            ->withHeaders($this->authHeaders($professionalUser))
            ->getJson('/api/v1/professional/bookings');

        $response
            ->assertOk()
            ->assertJsonCount(1, 'bookings')
            ->assertJsonPath('bookings.0.id', $booking->id);

        $this->assertNotSame($otherBooking->id, $response->json('bookings.0.id'));
    }

    public function test_user_without_professional_profile_cannot_list_professional_bookings(): void
    {
        $client = User::factory()->create();

        $response = $this
            ->withHeaders($this->authHeaders($client))
            ->getJson('/api/v1/professional/bookings');

        $response
            ->assertForbidden()
            ->assertJsonPath('error.type', 'Forbidden');
    }

    public function test_client_can_view_their_booking(): void
    {
        $client = User::factory()->create();
        $service = $this->createBookableService();
        $booking = $this->createBooking($service, $client);

        $response = $this
            ->withHeaders($this->authHeaders($client))
            ->getJson("/api/v1/bookings/{$booking->id}");

        $response
            ->assertOk()
            ->assertJsonPath('booking.id', $booking->id)
            ->assertJsonPath('booking.client.id', $client->id);
    }

    public function test_professional_can_view_booking_for_their_service(): void
    {
        [$professionalUser, $profile] = $this->createProfessional();
        $client = User::factory()->create();
        $service = $this->createBookableService([
            'professional_id' => $profile->id,
        ]);
        $booking = $this->createBooking($service, $client);

        $response = $this
            ->withHeaders($this->authHeaders($professionalUser))
            ->getJson("/api/v1/bookings/{$booking->id}");

        $response
            ->assertOk()
            ->assertJsonPath('booking.id', $booking->id)
            ->assertJsonPath('booking.professional.id', $profile->id);
    }

    public function test_stranger_cannot_view_booking(): void
    {
        $client = User::factory()->create();
        $stranger = User::factory()->create();
        $service = $this->createBookableService();
        $booking = $this->createBooking($service, $client);

        $response = $this
            ->withHeaders($this->authHeaders($stranger))
            ->getJson("/api/v1/bookings/{$booking->id}");

        $response
            ->assertForbidden()
            ->assertJsonPath('error.type', 'Forbidden');
    }

    public function test_professional_can_confirm_pending_booking(): void
    {
        [$professionalUser, $profile] = $this->createProfessional();
        $client = User::factory()->create();
        $service = $this->createBookableService([
            'professional_id' => $profile->id,
        ]);
        $booking = $this->createBooking($service, $client);

        $response = $this
            ->withHeaders($this->authHeaders($professionalUser))
            ->postJson("/api/v1/bookings/{$booking->id}/confirm");

        $response
            ->assertOk()
            ->assertJsonPath('booking.status', BookingStatus::Confirmed->value)
            ->assertJsonPath('booking.confirmed_at', '2026-06-01 12:00:00');

        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'status' => BookingStatus::Confirmed->value,
        ]);
    }

    public function test_client_cannot_confirm_booking(): void
    {
        $client = User::factory()->create();
        $service = $this->createBookableService();
        $booking = $this->createBooking($service, $client);

        $response = $this
            ->withHeaders($this->authHeaders($client))
            ->postJson("/api/v1/bookings/{$booking->id}/confirm");

        $response
            ->assertForbidden()
            ->assertJsonPath('error.type', 'Forbidden');
    }

    public function test_cancelled_booking_cannot_be_confirmed(): void
    {
        [$professionalUser, $profile] = $this->createProfessional();
        $client = User::factory()->create();
        $service = $this->createBookableService([
            'professional_id' => $profile->id,
        ]);
        $booking = $this->createBooking($service, $client, [
            'status' => BookingStatus::Cancelled,
            'cancelled_at' => now(),
        ]);

        $response = $this
            ->withHeaders($this->authHeaders($professionalUser))
            ->postJson("/api/v1/bookings/{$booking->id}/confirm");

        $response
            ->assertConflict()
            ->assertJsonPath('error.type', 'InvalidBookingStatusTransition');
    }

    public function test_client_can_cancel_booking(): void
    {
        $client = User::factory()->create();
        $service = $this->createBookableService();
        $booking = $this->createBooking($service, $client);

        $response = $this
            ->withHeaders($this->authHeaders($client))
            ->postJson("/api/v1/bookings/{$booking->id}/cancel", [
                'reason' => 'No puedo asistir',
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('booking.status', BookingStatus::Cancelled->value)
            ->assertJsonPath('booking.cancelled_at', '2026-06-01 12:00:00')
            ->assertJsonPath('booking.cancellation_reason', 'No puedo asistir');
    }

    public function test_professional_can_cancel_booking(): void
    {
        [$professionalUser, $profile] = $this->createProfessional();
        $client = User::factory()->create();
        $service = $this->createBookableService([
            'professional_id' => $profile->id,
        ]);
        $booking = $this->createBooking($service, $client);

        $response = $this
            ->withHeaders($this->authHeaders($professionalUser))
            ->postJson("/api/v1/bookings/{$booking->id}/cancel");

        $response
            ->assertOk()
            ->assertJsonPath('booking.status', BookingStatus::Cancelled->value);
    }

    public function test_stranger_cannot_cancel_booking(): void
    {
        $client = User::factory()->create();
        $stranger = User::factory()->create();
        $service = $this->createBookableService();
        $booking = $this->createBooking($service, $client);

        $response = $this
            ->withHeaders($this->authHeaders($stranger))
            ->postJson("/api/v1/bookings/{$booking->id}/cancel");

        $response
            ->assertForbidden()
            ->assertJsonPath('error.type', 'Forbidden');
    }

    public function test_cannot_cancel_after_minimum_window_expires(): void
    {
        $client = User::factory()->create();
        $service = $this->createBookableService([
            'min_reschedule_minutes' => 120,
        ]);
        $booking = $this->createBooking($service, $client, [
            'starts_at' => now()->addMinutes(30),
            'ends_at' => now()->addMinutes(90),
        ]);

        $response = $this
            ->withHeaders($this->authHeaders($client))
            ->postJson("/api/v1/bookings/{$booking->id}/cancel");

        $response
            ->assertConflict()
            ->assertJsonPath('error.type', 'CancellationWindowExpired');
    }

    public function test_client_can_reschedule_booking(): void
    {
        $client = User::factory()->create();
        $service = $this->createBookableService();
        $booking = $this->createBooking($service, $client);

        $response = $this
            ->withHeaders($this->authHeaders($client))
            ->postJson("/api/v1/bookings/{$booking->id}/reschedule", [
                'starts_at' => '2026-06-15 10:15:00',
                'reason' => 'Cambio de horario',
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('booking.starts_at', '2026-06-15 10:15:00')
            ->assertJsonPath('booking.ends_at', '2026-06-15 11:15:00')
            ->assertJsonPath('booking.status', BookingStatus::Pending->value)
            ->assertJsonPath('booking.reschedule_reason', 'Cambio de horario');
    }

    public function test_cannot_reschedule_to_occupied_slot(): void
    {
        $client = User::factory()->create();
        $otherClient = User::factory()->create();
        $service = $this->createBookableService();
        $booking = $this->createBooking($service, $client);

        $this->createBooking($service, $otherClient, [
            'starts_at' => '2026-06-15 10:15:00',
            'ends_at' => '2026-06-15 11:15:00',
        ]);

        $response = $this
            ->withHeaders($this->authHeaders($client))
            ->postJson("/api/v1/bookings/{$booking->id}/reschedule", [
                'starts_at' => '2026-06-15 10:15:00',
            ]);

        $response
            ->assertConflict()
            ->assertJsonPath('error.type', 'BookingSlotAlreadyTaken');
    }

    public function test_cannot_reschedule_to_invalid_slot(): void
    {
        $client = User::factory()->create();
        $service = $this->createBookableService();
        $booking = $this->createBooking($service, $client);

        $response = $this
            ->withHeaders($this->authHeaders($client))
            ->postJson("/api/v1/bookings/{$booking->id}/reschedule", [
                'starts_at' => '2026-06-15 11:30:00',
            ]);

        $response
            ->assertUnprocessable()
            ->assertJsonPath('error.type', 'InvalidBookingSlot');
    }

    private function createProfessional(): array
    {
        $user = User::factory()->professional()->create();
        $profile = ProfessionalProfile::factory()->create([
            'user_id' => $user->id,
        ]);

        return [$user, $profile];
    }

    private function createBookableService(array $overrides = []): Service
    {
        $service = Service::factory()->create([
            'duration_minutes' => 60,
            'buffer_minutes' => 15,
            'min_reschedule_minutes' => 10,
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

    private function authHeaders(User $user): array
    {
        $token = auth('user_jwt')->login($user);

        return [
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ];
    }
}
