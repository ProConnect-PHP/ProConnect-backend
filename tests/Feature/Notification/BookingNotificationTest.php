<?php

namespace Tests\Feature\Notification;

use App\Enums\Booking\BookingStatus;
use App\Events\Booking\BookingCancelled;
use App\Events\Booking\BookingConfirmed;
use App\Events\Booking\BookingCreated;
use App\Events\Booking\BookingRescheduled;
use App\Listeners\Booking\SendBookingCancelledNotification;
use App\Listeners\Booking\SendBookingConfirmedNotification;
use App\Listeners\Booking\SendBookingCreatedNotification;
use App\Listeners\Booking\SendBookingRescheduledNotification;
use App\Mail\Booking\BookingCancelledMail;
use App\Mail\Booking\BookingConfirmedForClientMail;
use App\Mail\Booking\BookingCreatedForClientMail;
use App\Mail\Booking\BookingCreatedForProfessionalMail;
use App\Mail\Booking\BookingRescheduledMail;
use App\Models\Availability\AvailabilityRule;
use App\Models\Booking\Booking;
use App\Models\Service\Service;
use App\Models\User\ProfessionalProfile;
use App\Models\User\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class BookingNotificationTest extends TestCase
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

    public function test_booking_created_event_is_dispatched_after_creating_booking(): void
    {
        $client = User::factory()->create();
        [, $profile] = $this->createProfessional();
        $service = $this->createBookableService([
            'professional_id' => $profile->id,
        ]);

        Event::fake([BookingCreated::class]);

        $response = $this
            ->withHeaders($this->authHeaders($client))
            ->postJson("/api/v1/services/{$service->id}/bookings", [
                'starts_at' => '2026-06-15 09:00:00',
            ]);

        $response->assertCreated();

        Event::assertDispatched(
            BookingCreated::class,
            fn (BookingCreated $event): bool => $event->booking->client_id === $client->id
                && $event->booking->service_id === $service->id
        );
    }

    public function test_booking_confirmed_event_is_dispatched_after_confirming_booking(): void
    {
        [$professionalUser, $profile] = $this->createProfessional();
        $client = User::factory()->create();
        $service = $this->createBookableService([
            'professional_id' => $profile->id,
        ]);
        $booking = $this->createBooking($service, $client);

        Event::fake([BookingConfirmed::class]);

        $response = $this
            ->withHeaders($this->authHeaders($professionalUser))
            ->postJson("/api/v1/bookings/{$booking->id}/confirm");

        $response->assertOk();

        Event::assertDispatched(
            BookingConfirmed::class,
            fn (BookingConfirmed $event): bool => $event->booking->id === $booking->id
        );
    }

    public function test_booking_cancelled_event_includes_actor(): void
    {
        $client = User::factory()->create();
        [, $profile] = $this->createProfessional();
        $service = $this->createBookableService([
            'professional_id' => $profile->id,
        ]);
        $booking = $this->createBooking($service, $client);

        Event::fake([BookingCancelled::class]);

        $response = $this
            ->withHeaders($this->authHeaders($client))
            ->postJson("/api/v1/bookings/{$booking->id}/cancel", [
                'reason' => 'Cambio de agenda',
            ]);

        $response->assertOk();

        Event::assertDispatched(
            BookingCancelled::class,
            fn (BookingCancelled $event): bool => $event->booking->id === $booking->id
                && $event->actor?->id === $client->id
        );
    }

    public function test_booking_rescheduled_event_includes_actor(): void
    {
        [$professionalUser, $profile] = $this->createProfessional();
        $client = User::factory()->create();
        $service = $this->createBookableService([
            'professional_id' => $profile->id,
        ]);
        $booking = $this->createBooking($service, $client);

        Event::fake([BookingRescheduled::class]);

        $response = $this
            ->withHeaders($this->authHeaders($professionalUser))
            ->postJson("/api/v1/bookings/{$booking->id}/reschedule", [
                'starts_at' => '2026-06-15 10:15:00',
                'reason' => 'Ajuste profesional',
            ]);

        $response->assertOk();

        Event::assertDispatched(
            BookingRescheduled::class,
            fn (BookingRescheduled $event): bool => $event->booking->id === $booking->id
                && $event->actor?->id === $professionalUser->id
        );
    }

    public function test_created_listener_sends_email_to_client_professional_and_logs(): void
    {
        Mail::fake();

        [$professionalUser, $profile] = $this->createProfessional();
        $client = User::factory()->create();
        $service = $this->createBookableService([
            'professional_id' => $profile->id,
        ]);
        $booking = $this->createBooking($service, $client)->load([
            'service',
            'professional.user',
            'client',
        ]);

        (new SendBookingCreatedNotification())->handle(new BookingCreated($booking));

        Mail::assertSent(
            BookingCreatedForClientMail::class,
            fn (BookingCreatedForClientMail $mail): bool => $mail->hasTo($client->email)
        );
        Mail::assertSent(
            BookingCreatedForProfessionalMail::class,
            fn (BookingCreatedForProfessionalMail $mail): bool => $mail->hasTo($professionalUser->email)
        );
        $this->assertDatabaseHas('notification_logs', [
            'booking_id' => $booking->id,
            'type' => 'booking_created_client',
            'recipient' => $client->email,
            'status' => 'sent',
        ]);
        $this->assertDatabaseHas('notification_logs', [
            'booking_id' => $booking->id,
            'type' => 'booking_created_professional',
            'recipient' => $professionalUser->email,
            'status' => 'sent',
        ]);
    }

    public function test_confirmed_listener_sends_email_to_client(): void
    {
        Mail::fake();

        [, $profile] = $this->createProfessional();
        $client = User::factory()->create();
        $service = $this->createBookableService([
            'professional_id' => $profile->id,
        ]);
        $booking = $this->createBooking($service, $client, [
            'status' => BookingStatus::Confirmed,
            'confirmed_at' => now(),
        ])->load(['service', 'professional.user', 'client']);

        (new SendBookingConfirmedNotification())->handle(new BookingConfirmed($booking));

        Mail::assertSent(
            BookingConfirmedForClientMail::class,
            fn (BookingConfirmedForClientMail $mail): bool => $mail->hasTo($client->email)
        );
    }

    public function test_cancelled_listener_sends_email_to_counterpart(): void
    {
        Mail::fake();

        [$professionalUser, $profile] = $this->createProfessional();
        $client = User::factory()->create();
        $service = $this->createBookableService([
            'professional_id' => $profile->id,
        ]);
        $booking = $this->createBooking($service, $client, [
            'status' => BookingStatus::Cancelled,
            'cancelled_at' => now(),
        ])->load(['service', 'professional.user', 'client']);

        (new SendBookingCancelledNotification())->handle(new BookingCancelled($booking, $client));

        Mail::assertSent(
            BookingCancelledMail::class,
            fn (BookingCancelledMail $mail): bool => $mail->hasTo($professionalUser->email)
        );
        Mail::assertNotSent(
            BookingCancelledMail::class,
            fn (BookingCancelledMail $mail): bool => $mail->hasTo($client->email)
        );
    }

    public function test_rescheduled_listener_sends_email_to_counterpart(): void
    {
        Mail::fake();

        [$professionalUser, $profile] = $this->createProfessional();
        $client = User::factory()->create();
        $service = $this->createBookableService([
            'professional_id' => $profile->id,
        ]);
        $booking = $this->createBooking($service, $client, [
            'starts_at' => '2026-06-15 10:15:00',
            'ends_at' => '2026-06-15 11:15:00',
            'reschedule_reason' => 'Ajuste profesional',
        ])->load(['service', 'professional.user', 'client']);

        (new SendBookingRescheduledNotification())->handle(new BookingRescheduled($booking, $professionalUser));

        Mail::assertSent(
            BookingRescheduledMail::class,
            fn (BookingRescheduledMail $mail): bool => $mail->hasTo($client->email)
        );
        Mail::assertNotSent(
            BookingRescheduledMail::class,
            fn (BookingRescheduledMail $mail): bool => $mail->hasTo($professionalUser->email)
        );
    }

    public function test_created_listener_does_not_fail_without_professional_user_relation(): void
    {
        Mail::fake();

        [, $profile] = $this->createProfessional();
        $client = User::factory()->create();
        $service = $this->createBookableService([
            'professional_id' => $profile->id,
        ]);
        $booking = $this->createBooking($service, $client)->load([
            'service',
            'professional',
            'client',
        ]);
        $booking->professional->setRelation('user', null);

        (new SendBookingCreatedNotification())->handle(new BookingCreated($booking));

        Mail::assertSent(
            BookingCreatedForClientMail::class,
            fn (BookingCreatedForClientMail $mail): bool => $mail->hasTo($client->email)
        );
        Mail::assertNotSent(BookingCreatedForProfessionalMail::class);
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
