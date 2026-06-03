<?php

namespace Tests\Feature\Notification;

use App\Enums\Booking\BookingStatus;
use App\Mail\Booking\BookingReminderMail;
use App\Models\Booking\Booking;
use App\Models\Service\Service;
use App\Models\User\ProfessionalProfile;
use App\Models\User\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
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

    public function test_sends_reminder_for_confirmed_booking_inside_window(): void
    {
        Mail::fake();

        [$booking, $client, $professionalUser] = $this->createBookingScenario([
            'status' => BookingStatus::Confirmed,
            'confirmed_at' => now(),
            'starts_at' => now()->copy()->addDay()->addMinutes(2),
            'ends_at' => now()->copy()->addDay()->addHour()->addMinutes(2),
        ]);

        $this->artisan('booking:send-reminders')
            ->assertExitCode(0);

        Mail::assertSent(
            BookingReminderMail::class,
            fn (BookingReminderMail $mail): bool => $mail->hasTo($client->email)
        );
        Mail::assertSent(
            BookingReminderMail::class,
            fn (BookingReminderMail $mail): bool => $mail->hasTo($professionalUser->email)
        );
        $this->assertNotNull($booking->refresh()->reminder_sent_at);
        $this->assertDatabaseHas('notification_logs', [
            'booking_id' => $booking->id,
            'type' => 'booking_reminder_24h',
            'recipient' => $client->email,
            'status' => 'sent',
        ]);
    }

    public function test_does_not_send_reminder_for_pending_booking(): void
    {
        Mail::fake();

        [$booking] = $this->createBookingScenario([
            'status' => BookingStatus::Pending,
            'starts_at' => now()->copy()->addDay()->addMinutes(2),
            'ends_at' => now()->copy()->addDay()->addHour()->addMinutes(2),
        ]);

        $this->artisan('booking:send-reminders')
            ->assertExitCode(0);

        Mail::assertNothingSent();
        $this->assertNull($booking->refresh()->reminder_sent_at);
    }

    public function test_does_not_send_reminder_for_cancelled_booking(): void
    {
        Mail::fake();

        [$booking] = $this->createBookingScenario([
            'status' => BookingStatus::Cancelled,
            'cancelled_at' => now(),
            'starts_at' => now()->copy()->addDay()->addMinutes(2),
            'ends_at' => now()->copy()->addDay()->addHour()->addMinutes(2),
        ]);

        $this->artisan('booking:send-reminders')
            ->assertExitCode(0);

        Mail::assertNothingSent();
        $this->assertNull($booking->refresh()->reminder_sent_at);
    }

    public function test_does_not_send_duplicate_reminder(): void
    {
        Mail::fake();

        [$booking] = $this->createBookingScenario([
            'status' => BookingStatus::Confirmed,
            'confirmed_at' => now(),
            'starts_at' => now()->copy()->addDay()->addMinutes(2),
            'ends_at' => now()->copy()->addDay()->addHour()->addMinutes(2),
            'reminder_sent_at' => now(),
        ]);

        $this->artisan('booking:send-reminders')
            ->assertExitCode(0);

        Mail::assertNothingSent();
        $this->assertNotNull($booking->refresh()->reminder_sent_at);
    }

    public function test_does_not_send_reminder_outside_window(): void
    {
        Mail::fake();

        [$booking] = $this->createBookingScenario([
            'status' => BookingStatus::Confirmed,
            'confirmed_at' => now(),
            'starts_at' => now()->copy()->addDay()->addMinutes(10),
            'ends_at' => now()->copy()->addDay()->addHour()->addMinutes(10),
        ]);

        $this->artisan('booking:send-reminders')
            ->assertExitCode(0);

        Mail::assertNothingSent();
        $this->assertNull($booking->refresh()->reminder_sent_at);
    }

    private function createBookingScenario(array $overrides = []): array
    {
        $professionalUser = User::factory()->professional()->create();
        $profile = ProfessionalProfile::factory()->create([
            'user_id' => $professionalUser->id,
        ]);
        $client = User::factory()->create();
        $service = Service::factory()->create([
            'professional_id' => $profile->id,
            'duration_minutes' => 60,
        ]);
        $booking = Booking::factory()->create([
            'service_id' => $service->id,
            'professional_id' => $profile->id,
            'client_id' => $client->id,
            'starts_at' => now()->copy()->addDay()->addMinutes(2),
            'ends_at' => now()->copy()->addDay()->addHour()->addMinutes(2),
            'status' => BookingStatus::Confirmed,
            'confirmed_at' => now(),
            'modality' => $service->modality,
            'price_snapshot' => $service->price,
            'duration_minutes_snapshot' => $service->duration_minutes,
            ...$overrides,
        ]);

        return [$booking, $client, $professionalUser];
    }
}
