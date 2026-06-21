<?php

namespace Tests\Feature\Booking;

use App\Enums\Booking\BookingStatus;
use App\Enums\Package\PackageSessionStatus;
use App\Events\Booking\BookingCompleted;
use App\Models\Booking\Booking;
use App\Models\Notification\Notification;
use App\Models\Package\ClientPackage;
use App\Models\Package\PackageSession;
use App\Models\Service\Service;
use App\Models\User\ProfessionalProfile;
use App\Models\User\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class CompleteBookingApiTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('queue.default', 'sync');

        Carbon::setTestNow('2026-06-01 12:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_professional_can_complete_own_confirmed_booking_after_start_time(): void
    {
        [$professional, , , $booking] = $this->bookingScenario(BookingStatus::Confirmed);

        $response = $this->complete($professional, $booking);

        $response
            ->assertOk()
            ->assertJsonPath('message', 'Booking completed successfully.')
            ->assertJsonPath('data.id', $booking->id)
            ->assertJsonPath('data.status', BookingStatus::Completed->value)
            ->assertJsonPath('data.completed_at', '2026-06-01 12:00:00');

        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'status' => BookingStatus::Completed->value,
        ]);
    }

    public function test_professional_can_complete_own_paid_booking_after_start_time(): void
    {
        [$professional, , , $booking] = $this->bookingScenario(BookingStatus::Paid);

        $this->complete($professional, $booking)
            ->assertOk()
            ->assertJsonPath('data.status', BookingStatus::Completed->value);
    }

    public function test_professional_can_complete_own_in_progress_booking(): void
    {
        [$professional, , , $booking] = $this->bookingScenario(BookingStatus::InProgress);

        $this->complete($professional, $booking)
            ->assertOk()
            ->assertJsonPath('data.status', BookingStatus::Completed->value);
    }

    public function test_guest_cannot_complete_booking(): void
    {
        [, , , $booking] = $this->bookingScenario(BookingStatus::Confirmed);

        $this->postJson("/api/v1/professional/bookings/{$booking->id}/complete")
            ->assertUnauthorized()
            ->assertJsonPath('error.type', 'Unauthorized');
    }

    public function test_professional_cannot_complete_another_professionals_booking(): void
    {
        [, , , $booking] = $this->bookingScenario(BookingStatus::Confirmed);
        [$otherProfessional] = $this->createProfessional();

        $this->complete($otherProfessional, $booking)
            ->assertForbidden()
            ->assertJsonPath('error.type', 'Forbidden');
    }

    public function test_client_cannot_complete_booking(): void
    {
        [, , $client, $booking] = $this->bookingScenario(BookingStatus::Confirmed);

        $this->complete($client, $booking)
            ->assertForbidden()
            ->assertJsonPath('error.type', 'Forbidden');
    }

    public function test_admin_cannot_complete_booking(): void
    {
        [, , , $booking] = $this->bookingScenario(BookingStatus::Confirmed);
        $admin = User::factory()->admin()->create();

        $this->complete($admin, $booking)
            ->assertForbidden()
            ->assertJsonPath('error.type', 'Forbidden');
    }

    public function test_cannot_complete_pending_booking(): void
    {
        [$professional, , , $booking] = $this->bookingScenario(BookingStatus::Pending);

        $this->assertCannotComplete($professional, $booking);
    }

    public function test_cannot_complete_cancelled_booking(): void
    {
        [$professional, , , $booking] = $this->bookingScenario(BookingStatus::Cancelled);

        $this->assertCannotComplete($professional, $booking);
    }

    public function test_cannot_complete_already_completed_booking(): void
    {
        [$professional, , , $booking] = $this->bookingScenario(BookingStatus::Completed);

        $this->assertCannotComplete($professional, $booking);
    }

    public function test_cannot_complete_no_show_booking(): void
    {
        [$professional, , , $booking] = $this->bookingScenario(BookingStatus::NoShow);

        $this->assertCannotComplete($professional, $booking);
    }

    public function test_cannot_complete_future_booking(): void
    {
        [$professional, , , $booking] = $this->bookingScenario(BookingStatus::Confirmed, [
            'starts_at' => now()->addHour(),
            'ends_at' => now()->addHours(2),
        ]);

        $this->assertCannotComplete($professional, $booking);
    }

    public function test_completing_package_booking_marks_session_consumed_without_refunding_it(): void
    {
        [$professional, , , $booking] = $this->bookingScenario(BookingStatus::Paid);
        [$clientPackage, $packageSession] = $this->attachReservedPackageSession($booking);

        $this->complete($professional, $booking)->assertOk();

        $this->assertSame(1, $clientPackage->refresh()->used_sessions);
        $this->assertSame(PackageSessionStatus::Consumed, $packageSession->refresh()->status);
        $this->assertNotNull($packageSession->consumed_at);
    }

    public function test_cancelling_package_booking_still_refunds_one_session(): void
    {
        [$professional, , $client, $booking] = $this->bookingScenario(BookingStatus::Confirmed, [
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDay()->addHour(),
        ]);
        [$clientPackage, $packageSession] = $this->attachReservedPackageSession($booking);

        $this
            ->withHeaders($this->authHeaders($client))
            ->postJson("/api/v1/bookings/{$booking->id}/cancel")
            ->assertOk()
            ->assertJsonPath('booking.status', BookingStatus::Cancelled->value);

        $this->assertSame(0, $clientPackage->refresh()->used_sessions);
        $this->assertSame(PackageSessionStatus::Released, $packageSession->refresh()->status);
        $this->assertNotNull($packageSession->released_at);
    }

    public function test_completion_creates_an_idempotent_client_notification(): void
    {
        [$professional, , $client, $booking] = $this->bookingScenario(BookingStatus::Confirmed);

        $this->complete($professional, $booking)->assertOk();

        $notification = Notification::query()
            ->where('recipient_id', $client->id)
            ->where('type', 'booking.completed')
            ->firstOrFail();

        $this->assertSame('Sesión finalizada', $notification->title);
        $this->assertSame('El profesional marcó la sesión como finalizada.', $notification->message);
        $this->assertSame("/my-bookings/{$booking->id}", $notification->action_route);
        $this->assertSame($booking->id, $notification->metadata['booking_id']);
        $this->assertDatabaseHas('notification_logs', [
            'booking_id' => $booking->id,
            'user_id' => $client->id,
            'channel' => 'database',
            'type' => 'booking.completed',
            'status' => 'sent',
        ]);
    }

    public function test_completion_enables_the_client_to_create_a_review(): void
    {
        [$professional, , $client, $booking] = $this->bookingScenario(BookingStatus::Confirmed);

        $this->complete($professional, $booking)->assertOk();

        $this
            ->withHeaders($this->authHeaders($client))
            ->postJson("/api/v1/bookings/{$booking->id}/review", [
                'rating' => 5,
                'comment' => 'La sesion fue excelente.',
            ])
            ->assertCreated()
            ->assertJsonPath('review.booking_id', $booking->id);
    }

    public function test_completion_dispatches_realtime_event_for_client_and_professional(): void
    {
        Event::fake([BookingCompleted::class]);

        [$professional, $profile, $client, $booking] = $this->bookingScenario(BookingStatus::Confirmed);

        $this->complete($professional, $booking)->assertOk();

        Event::assertDispatched(
            BookingCompleted::class,
            function (BookingCompleted $event) use ($booking, $professional, $profile, $client): bool {
                $channelNames = array_map(
                    static fn ($channel): string => $channel->name,
                    $event->broadcastOn()
                );

                return $event->booking->id === $booking->id
                    && $event->actor->id === $professional->id
                    && $event->broadcastAs() === 'booking.completed'
                    && in_array('private-bookings.client.'.$client->id, $channelNames, true)
                    && in_array('private-bookings.professional.'.$profile->id, $channelNames, true);
            }
        );
    }

    public function test_repeated_completion_is_rejected_and_dispatches_event_once(): void
    {
        Event::fake([BookingCompleted::class]);

        [$professional, , , $booking] = $this->bookingScenario(BookingStatus::Confirmed);

        $this->complete($professional, $booking)->assertOk();
        $this->assertCannotComplete($professional, $booking);

        Event::assertDispatchedTimes(BookingCompleted::class, 1);
    }

    private function assertCannotComplete(User $professional, Booking $booking): void
    {
        $originalStatus = $booking->refresh()->status;
        $originalCompletedAt = $booking->completed_at;

        $this->complete($professional, $booking)
            ->assertUnprocessable()
            ->assertJsonPath('error.type', 'BookingCannotBeCompleted');

        $booking->refresh();

        $this->assertSame($originalStatus, $booking->status);
        $this->assertEquals($originalCompletedAt, $booking->completed_at);
    }

    private function bookingScenario(BookingStatus $status, array $overrides = []): array
    {
        [$professional, $profile] = $this->createProfessional();
        $client = User::factory()->create();
        $service = Service::factory()->create([
            'professional_id' => $profile->id,
            'duration_minutes' => 60,
        ]);

        $booking = Booking::factory()->create([
            'service_id' => $service->id,
            'professional_id' => $profile->id,
            'client_id' => $client->id,
            'starts_at' => now()->subHour(),
            'ends_at' => now(),
            'status' => $status,
            'confirmed_at' => in_array($status, [BookingStatus::Confirmed, BookingStatus::Paid, BookingStatus::InProgress], true)
                ? now()->subDay()
                : null,
            'paid_at' => $status === BookingStatus::Paid ? now()->subDay() : null,
            'completed_at' => $status === BookingStatus::Completed ? now()->subMinute() : null,
            'cancelled_at' => $status === BookingStatus::Cancelled ? now()->subMinute() : null,
            'no_show_at' => $status === BookingStatus::NoShow ? now()->subMinute() : null,
            'modality' => $service->modality,
            'price_snapshot' => $service->price,
            'duration_minutes_snapshot' => $service->duration_minutes,
            ...$overrides,
        ]);

        return [$professional, $profile, $client, $booking];
    }

    private function attachReservedPackageSession(Booking $booking): array
    {
        $booking->loadMissing('service');

        $clientPackage = ClientPackage::factory()
            ->forService($booking->service)
            ->active()
            ->create([
                'client_id' => $booking->client_id,
                'total_sessions' => 4,
                'used_sessions' => 1,
            ]);
        $booking->update(['client_package_id' => $clientPackage->id]);

        $packageSession = PackageSession::factory()
            ->forClientPackage($clientPackage)
            ->forBooking($booking)
            ->reserved()
            ->create();

        return [$clientPackage, $packageSession];
    }

    private function complete(User $user, Booking $booking)
    {
        return $this
            ->withHeaders($this->authHeaders($user))
            ->postJson("/api/v1/professional/bookings/{$booking->id}/complete");
    }

    private function createProfessional(): array
    {
        $professional = User::factory()->professional()->create();
        $profile = ProfessionalProfile::factory()->create([
            'user_id' => $professional->id,
        ]);

        return [$professional, $profile];
    }

    private function authHeaders(User $user): array
    {
        return [
            'Authorization' => 'Bearer '.auth('user_jwt')->login($user),
            'Accept' => 'application/json',
        ];
    }
}
