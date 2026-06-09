<?php

namespace Tests\Feature\Notification;

use App\Enums\Booking\BookingReminderDeliveryStatus;
use App\Enums\Booking\BookingStatus;
use App\Jobs\Booking\SendBookingReminderJob;
use App\Models\Booking\Booking;
use App\Models\Booking\BookingReminderDelivery;
use App\Models\Booking\ProfessionalBookingReminderRule;
use App\Models\Service\Service;
use App\Models\User\ProfessionalProfile;
use App\Models\User\User;
use App\Notifications\BookingReminderNotification;
use App\Notifications\Channels\BookingReminderDatabaseChannel;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Tests\TestCase;

class BookingReminderCommandTest extends TestCase
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

    public function test_scheduler_creates_delivery_and_dispatches_matching_rule(): void
    {
        Queue::fake();
        [$booking] = $this->createBookingScenario();

        $this->artisan('bookings:dispatch-reminders')->assertExitCode(0);

        $delivery = BookingReminderDelivery::query()->firstOrFail();

        $this->assertSame($booking->id, $delivery->booking_id);
        $this->assertSame(BookingReminderDeliveryStatus::Processing, $delivery->status);
        Queue::assertPushed(
            SendBookingReminderJob::class,
            fn (SendBookingReminderJob $job): bool => $job->deliveryId === $delivery->id
        );
    }

    public function test_scheduler_does_not_create_delivery_when_reminders_are_disabled(): void
    {
        Queue::fake();
        [$booking, , $professional] = $this->createBookingScenario();
        $professional->bookingPolicy()->update(['reminders_enabled' => false]);

        $this->artisan('bookings:dispatch-reminders')->assertExitCode(0);

        $this->assertDatabaseCount('booking_reminder_deliveries', 0);
        Queue::assertNothingPushed();
    }

    public function test_scheduler_ignores_cancelled_and_completed_bookings(): void
    {
        Queue::fake();
        $this->createBookingScenario(['status' => BookingStatus::Cancelled]);
        $this->createBookingScenario(['status' => BookingStatus::Completed]);

        $this->artisan('bookings:dispatch-reminders')->assertExitCode(0);

        $this->assertDatabaseCount('booking_reminder_deliveries', 0);
        Queue::assertNothingPushed();
    }

    public function test_scheduler_does_not_duplicate_deliveries(): void
    {
        Queue::fake();
        $this->createBookingScenario();

        $this->artisan('bookings:dispatch-reminders')->assertExitCode(0);
        $this->artisan('bookings:dispatch-reminders')->assertExitCode(0);

        $this->assertDatabaseCount('booking_reminder_deliveries', 1);
        Queue::assertPushed(SendBookingReminderJob::class, 1);
    }

    public function test_job_notifies_selected_recipients_and_marks_delivery_sent(): void
    {
        Notification::fake();
        [$booking, $client, , $professionalUser, $rule] = $this->createBookingScenario();
        $delivery = $this->createDelivery($booking, $rule);

        (new SendBookingReminderJob($delivery->id))->handle();

        Notification::assertSentTo(
            $client,
            BookingReminderNotification::class,
            fn (BookingReminderNotification $notification): bool => $notification->recipientType === 'client'
        );
        Notification::assertSentTo(
            $professionalUser,
            BookingReminderNotification::class,
            fn (BookingReminderNotification $notification): bool => $notification->recipientType === 'professional'
        );
        $this->assertSame(
            BookingReminderDeliveryStatus::Sent,
            $delivery->refresh()->status
        );
        $this->assertNotNull($delivery->sent_at);
    }

    public function test_job_marks_delivery_skipped_when_booking_is_no_longer_eligible(): void
    {
        Notification::fake();
        [$booking, , , , $rule] = $this->createBookingScenario();
        $delivery = $this->createDelivery($booking, $rule);
        $booking->update([
            'status' => BookingStatus::Cancelled,
            'cancelled_at' => now(),
        ]);

        (new SendBookingReminderJob($delivery->id))->handle();

        Notification::assertNothingSent();
        $this->assertSame(
            BookingReminderDeliveryStatus::Skipped,
            $delivery->refresh()->status
        );
    }

    public function test_job_marks_delivery_failed_when_a_channel_throws(): void
    {
        [$booking, , , , $rule] = $this->createBookingScenario();
        $rule->update([
            'send_email' => false,
            'send_database_notification' => true,
        ]);
        $delivery = $this->createDelivery($booking, $rule);

        $this->mock(BookingReminderDatabaseChannel::class)
            ->shouldReceive('send')
            ->andThrow(new RuntimeException('Channel unavailable'));

        try {
            (new SendBookingReminderJob($delivery->id))->handle();
            $this->fail('Expected reminder channel failure.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Channel unavailable', $exception->getMessage());
        }

        $delivery->refresh();
        $this->assertSame(BookingReminderDeliveryStatus::Failed, $delivery->status);
        $this->assertSame('Channel unavailable', $delivery->failure_reason);
    }

    private function createBookingScenario(array $overrides = []): array
    {
        $professionalUser = User::factory()->professional()->create();
        $professional = ProfessionalProfile::factory()->create([
            'user_id' => $professionalUser->id,
        ]);
        $client = User::factory()->create();
        $service = Service::factory()->create([
            'professional_id' => $professional->id,
            'duration_minutes' => 60,
        ]);
        $booking = Booking::factory()->create([
            'service_id' => $service->id,
            'professional_id' => $professional->id,
            'client_id' => $client->id,
            'starts_at' => now()->addMinutes(120),
            'ends_at' => now()->addMinutes(180),
            'status' => BookingStatus::Confirmed,
            'confirmed_at' => now(),
            ...$overrides,
        ]);
        $rule = $professional->reminderRules()
            ->where('minutes_before_start', 120)
            ->firstOrFail();

        return [$booking, $client, $professional, $professionalUser, $rule];
    }

    private function createDelivery(
        Booking $booking,
        ProfessionalBookingReminderRule $rule
    ): BookingReminderDelivery {
        return BookingReminderDelivery::query()->create([
            'booking_id' => $booking->id,
            'reminder_rule_id' => $rule->id,
            'scheduled_for' => now(),
            'status' => BookingReminderDeliveryStatus::Processing,
        ]);
    }
}
