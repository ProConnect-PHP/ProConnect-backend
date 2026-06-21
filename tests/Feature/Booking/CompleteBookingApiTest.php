<?php

namespace Tests\Feature\Notification;

use App\Enums\Booking\BookingStatus;
use App\Events\Booking\BookingCompleted;
use App\Listeners\Booking\SendBookingCompletedNotification;
use App\Models\Booking\Booking;
use App\Models\Notification\Notification;
use App\Models\Service\Service;
use App\Models\User\ProfessionalProfile;
use App\Models\User\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SendBookingCompletedNotificationTest extends TestCase
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

    public function test_listener_creates_idempotent_client_notification(): void
    {
        [$professional, , $client, $booking] = $this->bookingScenario();

        $event = new BookingCompleted(
            booking: $booking,
            actor: $professional,
            previousStatus: BookingStatus::Confirmed,
        );

        $listener = app(SendBookingCompletedNotification::class);

        $listener->handle($event);
        $listener->handle($event);

        $this->assertSame(
            1,
            Notification::query()
                ->where('recipient_id', $client->id)
                ->where('type', 'booking.completed')
                ->count(),
        );

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

    private function bookingScenario(): array
    {
        $professional = User::factory()->professional()->create();

        $profile = ProfessionalProfile::factory()->create([
            'user_id' => $professional->id,
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
            'starts_at' => now()->subHour(),
            'ends_at' => now(),
            'status' => BookingStatus::Completed,
            'confirmed_at' => now()->subDay(),
            'completed_at' => now(),
            'modality' => $service->modality,
            'price_snapshot' => $service->price,
            'duration_minutes_snapshot' => $service->duration_minutes,
        ]);

        return [$professional, $profile, $client, $booking];
    }
}
