<?php

namespace Tests\Feature\Notification;

use App\Enums\Booking\BookingStatus;
use App\Events\Booking\BookingCancelled;
use App\Events\Booking\BookingConfirmed;
use App\Events\Booking\BookingCreated;
use App\Events\Booking\BookingRescheduled;
use App\Events\Notification\NotificationCreated;
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
use App\Models\Notification\Notification;
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

    public function test_client_cancellation_notifies_professional_in_app_with_metadata(): void
    {
        Mail::fake();
        Event::fake([NotificationCreated::class]);

        [$professionalUser, $profile] = $this->createProfessional();

        $client = User::factory()->create([
            'name' => 'Cliente Demo',
        ]);

        $service = $this->createBookableService([
            'professional_id' => $profile->id,
            'name' => 'Consulta estratégica',
        ]);

        $booking = $this->createBooking($service, $client);

        $this
            ->withHeaders($this->authHeaders($client))
            ->postJson("/api/v1/bookings/{$booking->id}/cancel", [
                'reason' => 'Cambio de agenda',
            ])
            ->assertOk()
            ->assertJsonPath('booking.status', BookingStatus::Cancelled->value);

        $this->runBookingCancelledNotificationListener($booking, $client);

        $notification = Notification::query()
            ->where('recipient_id', $professionalUser->id)
            ->where('type', 'booking.cancelled_by_client')
            ->firstOrFail();

        $this->assertSame('Reserva cancelada por el cliente', $notification->title);
        $this->assertStringContainsString('Cliente Demo canceló', $notification->message);

        $this->assertSame(
            "/professional/bookings/{$booking->id}",
            $notification->action_route
        );

        $this->assertSame($booking->id, $notification->metadata['booking_id']);
        $this->assertSame($service->id, $notification->metadata['service_id']);
        $this->assertSame('Consulta estratégica', $notification->metadata['service_name']);
        $this->assertSame($client->id, $notification->metadata['client_id']);
        $this->assertSame('Cliente Demo', $notification->metadata['client_name']);
        $this->assertSame($profile->id, $notification->metadata['professional_id']);
        $this->assertSame($professionalUser->name, $notification->metadata['professional_name']);
        $this->assertSame($client->id, $notification->metadata['cancelled_by']);
        $this->assertSame('client', $notification->metadata['cancelled_by_role']);
        $this->assertSame('Cliente Demo', $notification->metadata['cancelled_by_name']);
        $this->assertSame('Cambio de agenda', $notification->metadata['cancellation_reason']);
        $this->assertNotNull($notification->metadata['starts_at']);
        $this->assertNotNull($notification->metadata['ends_at']);
        $this->assertNotNull($notification->metadata['cancelled_at']);

        Event::assertDispatched(
            NotificationCreated::class,
            fn (NotificationCreated $event): bool => $event->notification->is($notification)
        );

        Event::assertDispatchedTimes(NotificationCreated::class, 2);

        $this
            ->withHeaders($this->authHeaders($professionalUser))
            ->getJson('/api/v1/notifications')
            ->assertOk()
            ->assertJsonPath('data.0.id', $notification->id)
            ->assertJsonPath('data.0.metadata.booking_id', $booking->id);
    }

    public function test_client_cancellation_creates_confirmation_for_client(): void
    {
        Mail::fake();

        [, $profile] = $this->createProfessional();

        $client = User::factory()->create();

        $service = $this->createBookableService([
            'professional_id' => $profile->id,
        ]);

        $booking = $this->createBooking($service, $client);

        $this
            ->withHeaders($this->authHeaders($client))
            ->postJson("/api/v1/bookings/{$booking->id}/cancel", [
                'reason' => 'Cambio de agenda',
            ])
            ->assertOk();

        $this->runBookingCancelledNotificationListener($booking, $client);

        $confirmation = Notification::query()
            ->where('recipient_id', $client->id)
            ->where('type', 'booking.cancelled_by_client_confirmation')
            ->firstOrFail();

        $this->assertSame('Has cancelado tu reserva', $confirmation->title);

        $this->assertStringContainsString(
            'Has cancelado tu reserva',
            $confirmation->message
        );

        $this->assertSame("/my-bookings/{$booking->id}", $confirmation->action_route);
        $this->assertSame('client', $confirmation->metadata['cancelled_by_role']);
        $this->assertSame($client->id, $confirmation->metadata['cancelled_by']);

        $this->assertSame(
            $booking->refresh()->cancelled_at?->toISOString(),
            $confirmation->metadata['cancelled_at']
        );

        $this
            ->withHeaders($this->authHeaders($client))
            ->getJson('/api/v1/notifications')
            ->assertOk()
            ->assertJsonPath('data.0.id', $confirmation->id);

        $this->assertDatabaseHas('notification_logs', [
            'booking_id' => $booking->id,
            'user_id' => $client->id,
            'channel' => 'database',
            'type' => 'booking.cancelled_by_client_confirmation',
            'status' => 'sent',
        ]);
    }

    public function test_professional_cancellation_notifies_client_in_app(): void
    {
        Mail::fake();
        Event::fake([NotificationCreated::class]);

        [$professionalUser, $profile] = $this->createProfessional();

        $client = User::factory()->create();

        $service = $this->createBookableService([
            'professional_id' => $profile->id,
        ]);

        $booking = $this->createBooking($service, $client);

        $this
            ->withHeaders($this->authHeaders($professionalUser))
            ->postJson("/api/v1/bookings/{$booking->id}/cancel", [
                'reason' => 'Indisponibilidad profesional',
            ])
            ->assertOk();

        $this->runBookingCancelledNotificationListener($booking, $professionalUser);

        $notification = Notification::query()
            ->where('recipient_id', $client->id)
            ->where('type', 'booking.cancelled_by_professional')
            ->firstOrFail();

        $this->assertSame(
            'Reserva cancelada por el profesional',
            $notification->title
        );

        $this->assertStringContainsString(
            'canceló tu reserva',
            $notification->message
        );

        $this->assertSame('professional', $notification->metadata['cancelled_by_role']);

        $this->assertSame(
            $professionalUser->id,
            $notification->metadata['cancelled_by']
        );

        $this->assertSame(
            'Indisponibilidad profesional',
            $notification->metadata['cancellation_reason']
        );

        $this->assertSame(
            "/my-bookings/{$booking->id}",
            $notification->action_route
        );

        Event::assertDispatchedTimes(NotificationCreated::class, 2);
    }

    public function test_professional_cancellation_creates_confirmation_for_professional(): void
    {
        Mail::fake();

        [$professionalUser, $profile] = $this->createProfessional();

        $client = User::factory()->create([
            'name' => 'Cliente Demo',
        ]);

        $service = $this->createBookableService([
            'professional_id' => $profile->id,
        ]);

        $booking = $this->createBooking($service, $client);

        $this
            ->withHeaders($this->authHeaders($professionalUser))
            ->postJson("/api/v1/bookings/{$booking->id}/cancel")
            ->assertOk();

        $this->runBookingCancelledNotificationListener($booking, $professionalUser);

        $confirmation = Notification::query()
            ->where('recipient_id', $professionalUser->id)
            ->where('type', 'booking.cancelled_by_professional_confirmation')
            ->firstOrFail();

        $this->assertSame('Has cancelado una reserva', $confirmation->title);

        $this->assertStringContainsString(
            'Has cancelado la reserva de Cliente Demo',
            $confirmation->message
        );

        $this->assertSame(
            "/professional/bookings/{$booking->id}",
            $confirmation->action_route
        );

        $this->assertSame(
            'professional',
            $confirmation->metadata['cancelled_by_role']
        );

        $this->assertSame(
            $professionalUser->id,
            $confirmation->metadata['cancelled_by']
        );
    }

    public function test_stranger_cannot_cancel_or_create_notification(): void
    {
        Mail::fake();

        $client = User::factory()->create();
        $stranger = User::factory()->create();
        $service = $this->createBookableService();
        $booking = $this->createBooking($service, $client);

        $this
            ->withHeaders($this->authHeaders($stranger))
            ->postJson("/api/v1/bookings/{$booking->id}/cancel")
            ->assertForbidden();

        $this->assertDatabaseCount('notifications', 0);
        $this->assertDatabaseCount('notification_logs', 0);
    }

    public function test_repeated_cancellation_does_not_duplicate_notifications(): void
    {
        Mail::fake();

        [$professionalUser, $profile] = $this->createProfessional();

        $client = User::factory()->create();

        $service = $this->createBookableService([
            'professional_id' => $profile->id,
        ]);

        $booking = $this->createBooking($service, $client);

        $url = "/api/v1/bookings/{$booking->id}/cancel";

        $this
            ->withHeaders($this->authHeaders($client))
            ->postJson($url)
            ->assertOk();

        $this->runBookingCancelledNotificationListener($booking, $client);

        $this
            ->withHeaders($this->authHeaders($client))
            ->postJson($url)
            ->assertConflict();

        $this->assertSame(
            1,
            Notification::query()
                ->where('recipient_id', $professionalUser->id)
                ->where('type', 'booking.cancelled_by_client')
                ->count()
        );

        $this->assertSame(
            1,
            Notification::query()
                ->where('recipient_id', $client->id)
                ->where('type', 'booking.cancelled_by_client_confirmation')
                ->count()
        );

        $this->assertDatabaseHas('notification_logs', [
            'booking_id' => $booking->id,
            'user_id' => $professionalUser->id,
            'channel' => 'database',
            'type' => 'booking.cancelled_by_client',
            'status' => 'sent',
        ]);

        $this->assertDatabaseHas('notification_logs', [
            'booking_id' => $booking->id,
            'user_id' => $client->id,
            'channel' => 'database',
            'type' => 'booking.cancelled_by_client_confirmation',
            'status' => 'sent',
        ]);
    }

    public function test_repeated_cancellation_dispatches_cancelled_event_only_once(): void
    {
        Event::fake([
            BookingCancelled::class,
        ]);

        [$professionalUser, $profile] = $this->createProfessional();

        $client = User::factory()->create();

        $service = $this->createBookableService([
            'professional_id' => $profile->id,
        ]);

        $booking = $this->createBooking($service, $client);

        $url = "/api/v1/bookings/{$booking->id}/cancel";

        $this
            ->withHeaders($this->authHeaders($client))
            ->postJson($url)
            ->assertOk();

        $this
            ->withHeaders($this->authHeaders($client))
            ->postJson($url)
            ->assertConflict();

        Event::assertDispatchedTimes(BookingCancelled::class, 1);
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

        (new SendBookingCreatedNotification)->handle(new BookingCreated($booking));

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

        (new SendBookingConfirmedNotification)->handle(new BookingConfirmed($booking));

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

        $listener = new SendBookingCancelledNotification;
        $event = new BookingCancelled($booking, $client);

        $listener->handle($event);
        $listener->handle($event);

        Mail::assertSent(
            BookingCancelledMail::class,
            fn (BookingCancelledMail $mail): bool => $mail->hasTo($professionalUser->email)
        );
        Mail::assertNotSent(
            BookingCancelledMail::class,
            fn (BookingCancelledMail $mail): bool => $mail->hasTo($client->email)
        );
        Mail::assertSent(BookingCancelledMail::class, 1);
        $this->assertSame(
            1,
            Notification::query()
                ->where('recipient_id', $professionalUser->id)
                ->where('type', 'booking.cancelled_by_client')
                ->count()
        );
        $this->assertSame(
            1,
            Notification::query()
                ->where('recipient_id', $client->id)
                ->where('type', 'booking.cancelled_by_client_confirmation')
                ->count()
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

        (new SendBookingRescheduledNotification)->handle(new BookingRescheduled($booking, $professionalUser));

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

        (new SendBookingCreatedNotification)->handle(new BookingCreated($booking));

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

    private function runBookingCancelledNotificationListener(Booking $booking, User $actor): void
    {
        app(SendBookingCancelledNotification::class)->handle(
            new BookingCancelled(
                booking: $booking->fresh([
                    'service',
                    'professional.user',
                    'client',
                ]),
                actor: $actor,
            )
        );
    }
}
