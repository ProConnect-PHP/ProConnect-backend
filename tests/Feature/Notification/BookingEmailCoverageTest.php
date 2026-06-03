<?php

namespace Tests\Feature\Notification;

use App\Enums\Booking\BookingStatus;
use App\Events\Booking\BookingCreated;
use App\Listeners\Booking\SendBookingCreatedNotification;
use App\Mail\Booking\BookingCreatedForClientMail;
use App\Mail\Booking\BookingCreatedForProfessionalMail;
use App\Models\Booking\Booking;
use App\Models\Notification\NotificationLog;
use App\Models\Service\Service;
use App\Models\User\ProfessionalProfile;
use App\Models\User\User;
use App\Support\Booking\BookingLocationPresenter;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class BookingEmailCoverageTest extends TestCase
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

    public function test_booking_created_sends_email_to_client_and_professional_without_duplicate_logs(): void
    {
        Mail::fake();

        [$booking, $client, $professionalUser] = $this->createBookingScenario();
        $event = new BookingCreated($booking->load(['service', 'professional.user', 'client']));
        $listener = new SendBookingCreatedNotification();

        $listener->handle($event);
        $listener->handle($event);

        Mail::assertSent(
            BookingCreatedForClientMail::class,
            1
        );
        Mail::assertSent(
            BookingCreatedForProfessionalMail::class,
            1
        );
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
            'user_id' => $client->id,
            'type' => 'booking_created_client',
            'status' => 'sent',
        ]);
        $this->assertDatabaseHas('notification_logs', [
            'booking_id' => $booking->id,
            'user_id' => $professionalUser->id,
            'type' => 'booking_created_professional',
            'status' => 'sent',
        ]);
        $this->assertSame(
            2,
            NotificationLog::query()
                ->where('booking_id', $booking->id)
                ->whereIn('type', [
                    'booking_created_client',
                    'booking_created_professional',
                ])
                ->count()
        );
    }

    public function test_created_for_client_email_renders_address_and_map_link_for_physical_booking(): void
    {
        [$booking] = $this->createBookingScenario([
            'service' => [
                'modality' => 'presencial',
                'address' => 'Av. 18 de Julio 1234, Montevideo',
                'latitude' => -34.9011,
                'longitude' => -56.1645,
            ],
        ]);

        $html = (new BookingCreatedForClientMail($booking->load(['service', 'professional.user', 'client'])))->render();

        $this->assertStringContainsString('Direccion', $html);
        $this->assertStringContainsString('Av. 18 de Julio 1234, Montevideo', $html);
        $this->assertStringContainsString('Ver ubicacion en mapa', $html);
        $this->assertStringContainsString('google.com/maps/search', $html);
    }

    public function test_created_for_client_email_does_not_render_location_for_remote_booking(): void
    {
        [$booking] = $this->createBookingScenario([
            'service' => [
                'modality' => 'remota',
                'address' => null,
                'latitude' => null,
                'longitude' => null,
            ],
        ]);

        $html = (new BookingCreatedForClientMail($booking->load(['service', 'professional.user', 'client'])))->render();

        $this->assertStringNotContainsString('Ver ubicacion', $html);
        $this->assertStringNotContainsString('Direccion', $html);
    }

    public function test_static_map_url_is_null_without_mapbox_token(): void
    {
        config()->set('services.mapbox.public_token', null);

        [$booking] = $this->createBookingScenario([
            'service' => [
                'modality' => 'presencial',
                'address' => 'Montevideo, Uruguay',
                'latitude' => -34.9011,
                'longitude' => -56.1645,
            ],
        ]);

        $this->assertNull(
            BookingLocationPresenter::staticMapImageUrl($booking->load('service'))
        );
    }

    private function createBookingScenario(array $overrides = []): array
    {
        $professionalUser = User::factory()->professional()->create();
        $profile = ProfessionalProfile::factory()->create([
            'user_id' => $professionalUser->id,
        ]);
        $client = User::factory()->create();
        $serviceOverrides = $overrides['service'] ?? [];
        $service = Service::factory()->create([
            'professional_id' => $profile->id,
            'duration_minutes' => 60,
            'modality' => 'hibrida',
            'address' => 'Montevideo, Uruguay',
            'latitude' => -34.9011,
            'longitude' => -56.1645,
            ...$serviceOverrides,
        ]);
        $booking = Booking::factory()->create([
            'service_id' => $service->id,
            'professional_id' => $profile->id,
            'client_id' => $client->id,
            'starts_at' => '2026-06-15 09:00:00',
            'ends_at' => '2026-06-15 10:00:00',
            'status' => BookingStatus::Pending,
            'modality' => $service->modality,
            'price_snapshot' => $service->price,
            'duration_minutes_snapshot' => $service->duration_minutes,
            ...collect($overrides)->except('service')->all(),
        ]);

        return [$booking, $client, $professionalUser];
    }
}
