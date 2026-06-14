<?php

namespace Tests\Feature\Notification;

use App\Enums\Booking\BookingStatus;
use App\Models\Availability\AvailabilityRule;
use App\Models\Booking\Booking;
use App\Models\Notification\Notification;
use App\Models\Service\Service;
use App\Models\User\ProfessionalProfile;
use App\Models\User\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class BookingNotificationRouteTest extends TestCase
{
    use DatabaseMigrations;

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

    public function test_booking_creation_notifies_professional_with_detail_route_and_metadata(): void
    {
        Mail::fake();

        [$professionalUser, $profile] = $this->createProfessional();
        $client = User::factory()->create();
        $service = $this->createBookableService($profile, 'Consulta inicial');

        $response = $this
            ->withHeaders($this->authHeaders($client))
            ->postJson("/api/v1/services/{$service->id}/bookings", [
                'starts_at' => '2026-06-15 09:00:00',
            ])
            ->assertCreated();

        $bookingId = $response->json('booking.id');
        $notification = Notification::query()
            ->where('recipient_id', $professionalUser->id)
            ->where('type', 'booking.created')
            ->firstOrFail();

        $this->assertSame("/professional/bookings/{$bookingId}", $notification->action_route);
        $this->assertBookingMetadata($notification, $bookingId, $service, $client, $profile);
    }

    public function test_booking_confirmation_notifies_client_with_detail_route_and_metadata(): void
    {
        Mail::fake();

        [$professionalUser, $profile] = $this->createProfessional();
        $client = User::factory()->create();
        $service = $this->createBookableService($profile, 'Terapia online individual');
        $booking = $this->createBooking($service, $client);

        $this
            ->withHeaders($this->authHeaders($professionalUser))
            ->postJson("/api/v1/bookings/{$booking->id}/confirm")
            ->assertOk();

        $notification = Notification::query()
            ->where('recipient_id', $client->id)
            ->where('type', 'booking.confirmed')
            ->firstOrFail();

        $this->assertSame("/my-bookings/{$booking->id}", $notification->action_route);
        $this->assertBookingMetadata($notification, $booking->id, $service, $client, $profile);
    }

    public function test_professional_reschedule_notifies_client_with_client_detail_route(): void
    {
        Mail::fake();

        [$professionalUser, $profile] = $this->createProfessional();
        $client = User::factory()->create();
        $service = $this->createBookableService($profile);
        $booking = $this->createBooking($service, $client);

        $this
            ->withHeaders($this->authHeaders($professionalUser))
            ->postJson("/api/v1/bookings/{$booking->id}/reschedule", [
                'starts_at' => '2026-06-15 10:15:00',
                'reason' => 'Ajuste profesional',
            ])
            ->assertOk();

        $notification = Notification::query()
            ->where('recipient_id', $client->id)
            ->where('type', 'booking.rescheduled')
            ->firstOrFail();

        $this->assertSame("/my-bookings/{$booking->id}", $notification->action_route);
        $this->assertBookingMetadata($notification, $booking->id, $service, $client, $profile);
    }

    public function test_client_reschedule_notifies_professional_with_professional_detail_route(): void
    {
        Mail::fake();

        [$professionalUser, $profile] = $this->createProfessional();
        $client = User::factory()->create();
        $service = $this->createBookableService($profile);
        $booking = $this->createBooking($service, $client);

        $this
            ->withHeaders($this->authHeaders($client))
            ->postJson("/api/v1/bookings/{$booking->id}/reschedule", [
                'starts_at' => '2026-06-15 10:15:00',
                'reason' => 'Ajuste cliente',
            ])
            ->assertOk();

        $notification = Notification::query()
            ->where('recipient_id', $professionalUser->id)
            ->where('type', 'booking.rescheduled')
            ->firstOrFail();

        $this->assertSame("/professional/bookings/{$booking->id}", $notification->action_route);
        $this->assertBookingMetadata($notification, $booking->id, $service, $client, $profile);
    }

    private function assertBookingMetadata(
        Notification $notification,
        string $bookingId,
        Service $service,
        User $client,
        ProfessionalProfile $profile
    ): void {
        $this->assertSame($bookingId, $notification->metadata['booking_id']);
        $this->assertSame($service->id, $notification->metadata['service_id']);
        $this->assertSame($service->name, $notification->metadata['service_name']);
        $this->assertSame($client->id, $notification->metadata['client_id']);
        $this->assertSame($client->name, $notification->metadata['client_name']);
        $this->assertSame($profile->id, $notification->metadata['professional_id']);
        $this->assertSame(
            $profile->user->name,
            $notification->metadata['professional_name']
        );
        $this->assertNotNull($notification->metadata['starts_at']);
        $this->assertNotNull($notification->metadata['ends_at']);
    }

    private function createProfessional(): array
    {
        $user = User::factory()->professional()->create();
        $profile = ProfessionalProfile::factory()->create([
            'user_id' => $user->id,
        ]);
        $profile->setRelation('user', $user);

        return [$user, $profile];
    }

    private function createBookableService(
        ProfessionalProfile $profile,
        string $name = 'Consulta profesional'
    ): Service {
        $service = Service::factory()->create([
            'professional_id' => $profile->id,
            'name' => $name,
            'duration_minutes' => 60,
            'buffer_minutes' => 15,
            'min_reschedule_minutes' => 10,
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

    private function createBooking(Service $service, User $client): Booking
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
