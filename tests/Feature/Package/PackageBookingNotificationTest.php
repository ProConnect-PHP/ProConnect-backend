<?php

namespace Tests\Feature\Package;

use App\Enums\Booking\BookingStatus;
use App\Events\Booking\BookingCreated;
use App\Events\Package\PackageSessionReserved;
use App\Listeners\Booking\SendBookingCreatedNotification;
use App\Listeners\Package\SendPackageSessionReservedNotifications;
use App\Mail\Booking\BookingCreatedForClientMail;
use App\Mail\Booking\BookingCreatedForProfessionalMail;
use App\Mail\Package\PackageSessionReservedForClientMail;
use App\Mail\Package\PackageSessionReservedForProfessionalMail;
use App\Models\Availability\AvailabilityRule;
use App\Models\Booking\Booking;
use App\Models\Notification\NotificationLog;
use App\Models\Package\ClientPackage;
use App\Models\Package\PackageProduct;
use App\Models\Package\PackageSession;
use App\Models\Service\Service;
use App\Models\User\ProfessionalProfile;
use App\Models\User\User;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PackageBookingNotificationTest extends TestCase
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

    public function test_booking_with_package_dispatches_package_session_reserved_after_commit(): void
    {
        Event::fake([BookingCreated::class, PackageSessionReserved::class]);
        [$client, $service, $clientPackage] = $this->bookablePackageScenario();

        $response = $this
            ->withHeaders($this->authHeaders($client))
            ->postJson("/api/v1/services/{$service->id}/bookings", [
                'starts_at' => '2026-06-15 09:00:00',
                'client_package_id' => $clientPackage->id,
            ]);

        $response->assertCreated();

        Event::assertDispatched(BookingCreated::class);
        Event::assertDispatched(
            PackageSessionReserved::class,
            function (PackageSessionReserved $event) use ($clientPackage): bool {
                $session = PackageSession::query()->find($event->packageSessionId);

                return $session instanceof PackageSession
                    && $session->client_package_id === $clientPackage->id;
            }
        );
    }

    public function test_package_session_reserved_listener_sends_emails_and_is_idempotent(): void
    {
        Mail::fake();
        [$packageSession, $client, $professionalUser] = $this->packageSessionScenario();
        $listener = new SendPackageSessionReservedNotifications();

        $listener->handle(new PackageSessionReserved($packageSession->id));
        $listener->handle(new PackageSessionReserved($packageSession->id));

        Mail::assertSent(PackageSessionReservedForClientMail::class, 1);
        Mail::assertSent(PackageSessionReservedForProfessionalMail::class, 1);
        Mail::assertSent(
            PackageSessionReservedForClientMail::class,
            fn (PackageSessionReservedForClientMail $mail): bool => $mail->hasTo($client->email)
        );
        Mail::assertSent(
            PackageSessionReservedForProfessionalMail::class,
            fn (PackageSessionReservedForProfessionalMail $mail): bool => $mail->hasTo($professionalUser->email)
        );

        $this->assertDatabaseHas('notification_logs', [
            'booking_id' => $packageSession->booking_id,
            'client_package_id' => $packageSession->client_package_id,
            'package_session_id' => $packageSession->id,
            'user_id' => $client->id,
            'type' => 'package_session_reserved_client',
            'status' => 'sent',
        ]);
        $this->assertDatabaseHas('notification_logs', [
            'booking_id' => $packageSession->booking_id,
            'client_package_id' => $packageSession->client_package_id,
            'package_session_id' => $packageSession->id,
            'user_id' => $professionalUser->id,
            'type' => 'package_session_reserved_professional',
            'status' => 'sent',
        ]);
        $this->assertSame(
            2,
            NotificationLog::query()
                ->where('package_session_id', $packageSession->id)
                ->whereIn('type', [
                    'package_session_reserved_client',
                    'package_session_reserved_professional',
                ])
                ->count()
        );
    }

    public function test_booking_with_package_does_not_send_generic_booking_created_email(): void
    {
        Mail::fake();
        [$packageSession] = $this->packageSessionScenario();
        $booking = $packageSession->booking->load([
            'service',
            'professional.user',
            'client',
            'clientPackage',
        ]);

        (new SendBookingCreatedNotification())->handle(new BookingCreated($booking));

        Mail::assertNotSent(BookingCreatedForClientMail::class);
        Mail::assertNotSent(BookingCreatedForProfessionalMail::class);
        $this->assertDatabaseMissing('notification_logs', [
            'booking_id' => $booking->id,
            'type' => 'booking_created_client',
        ]);
        $this->assertDatabaseMissing('notification_logs', [
            'booking_id' => $booking->id,
            'type' => 'booking_created_professional',
        ]);
    }

    public function test_package_session_reserved_email_contains_remaining_sessions(): void
    {
        [$packageSession] = $this->packageSessionScenario();

        $html = (new PackageSessionReservedForClientMail($packageSession->load([
            'client',
            'professional.user',
            'clientPackage.packageProduct.service',
            'clientPackage.service',
            'booking.service',
        ])))->render();

        $this->assertStringContainsString('Reserva realizada usando tu paquete', $html);
        $this->assertStringContainsString('Pack 4 sesiones online', $html);
        $this->assertStringContainsString('Sesiones restantes', $html);
        $this->assertStringContainsString('No se generó un cobro individual', $html);
    }

    public function test_package_session_reserved_listener_is_queued_after_commit(): void
    {
        $listener = new SendPackageSessionReservedNotifications();

        $this->assertInstanceOf(ShouldQueue::class, $listener);
        $this->assertSame('emails', $listener->queue);
        $this->assertTrue($listener->afterCommit);
    }

    private function packageSessionScenario(): array
    {
        [$client, $service, $clientPackage, $professionalUser] = $this->bookablePackageScenario([
            'used_sessions' => 1,
        ]);
        $booking = Booking::factory()->create([
            'service_id' => $service->id,
            'professional_id' => $service->professional_id,
            'client_id' => $client->id,
            'client_package_id' => $clientPackage->id,
            'starts_at' => '2026-06-15 09:00:00',
            'ends_at' => '2026-06-15 10:00:00',
            'status' => BookingStatus::Pending,
            'modality' => $service->modality,
            'price_snapshot' => $service->price,
            'duration_minutes_snapshot' => $service->duration_minutes,
        ]);
        $packageSession = PackageSession::factory()
            ->forClientPackage($clientPackage)
            ->forBooking($booking)
            ->reserved()
            ->create();

        return [$packageSession, $client, $professionalUser];
    }

    private function bookablePackageScenario(array $overrides = []): array
    {
        $client = User::factory()->create();
        $professionalUser = User::factory()->professional()->create();
        $professional = ProfessionalProfile::factory()->create([
            'user_id' => $professionalUser->id,
        ]);
        $service = Service::factory()->create([
            'professional_id' => $professional->id,
            'name' => 'Terapia online individual',
            'price' => 1600,
            'duration_minutes' => 60,
            'buffer_minutes' => 15,
            'is_active' => true,
        ]);
        AvailabilityRule::factory()->create([
            'service_id' => $service->id,
            'day_of_week' => 1,
            'start_time' => '09:00',
            'end_time' => '12:00',
            'is_active' => true,
        ]);
        $packageProduct = PackageProduct::factory()
            ->forService($service)
            ->active()
            ->create([
                'name' => 'Pack 4 sesiones online',
                'sessions_count' => 4,
                'price' => 5600,
                'validity_days' => 60,
            ]);
        $clientPackage = ClientPackage::factory()
            ->forPackageProduct($packageProduct)
            ->active()
            ->create([
                'client_id' => $client->id,
                'total_sessions' => 4,
                'used_sessions' => $overrides['used_sessions'] ?? 0,
            ]);

        return [$client, $service, $clientPackage, $professionalUser];
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
