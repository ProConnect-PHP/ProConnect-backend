<?php

namespace Tests\Feature\Video;

use App\Actions\Booking\CancelBookingAction;
use App\Actions\Booking\ConfirmBookingAction;
use App\Actions\Payment\SimulatePaymentSuccessAction;
use App\Actions\Video\EndVideoSessionAction;
use App\Actions\Video\EnsureVideoSessionForBookingAction;
use App\Enums\Booking\BookingStatus;
use App\Enums\Video\VideoSessionStatus;
use App\Events\Payment\PaymentSucceeded;
use App\Models\Booking\Booking;
use App\Models\Package\ClientPackage;
use App\Models\Package\PackageSession;
use App\Models\Payment\PaymentIntent;
use App\Models\Service\Service;
use App\Models\User\ProfessionalProfile;
use App\Models\User\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class VideoSessionLifecycleTest extends TestCase
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

    public function test_confirm_unpaid_booking_does_not_create_video_session(): void
    {
        [$booking] = $this->bookingScenario(['status' => BookingStatus::Pending]);

        $confirmed = app(ConfirmBookingAction::class)($booking);

        $this->assertSame(BookingStatus::Confirmed, $confirmed->status);
        $this->assertNull($confirmed->videoSession);
        $this->assertDatabaseMissing('video_sessions', [
            'booking_id' => $booking->id,
        ]);
    }

    public function test_confirm_package_covered_booking_creates_video_session(): void
    {
        [$booking] = $this->bookingScenario(['status' => BookingStatus::Pending]);
        $clientPackage = ClientPackage::factory()
            ->forService($booking->service)
            ->active()
            ->create([
                'client_id' => $booking->client_id,
            ]);
        $booking->update(['client_package_id' => $clientPackage->id]);
        PackageSession::factory()
            ->forClientPackage($clientPackage)
            ->forBooking($booking)
            ->reserved()
            ->create();

        app(ConfirmBookingAction::class)($booking);

        $this->assertDatabaseHas('video_sessions', [
            'booking_id' => $booking->id,
            'status' => VideoSessionStatus::Scheduled->value,
        ]);
    }

    public function test_confirm_booking_does_not_create_video_session_for_in_person_booking(): void
    {
        [$booking] = $this->bookingScenario([
            'status' => BookingStatus::Pending,
            'modality' => 'presencial',
        ]);

        app(ConfirmBookingAction::class)($booking);

        $this->assertDatabaseMissing('video_sessions', [
            'booking_id' => $booking->id,
        ]);
    }

    public function test_payment_success_ensures_video_session_when_missing(): void
    {
        Event::fake([PaymentSucceeded::class]);
        [$booking, $client] = $this->bookingScenario(['status' => BookingStatus::Confirmed]);
        $paymentIntent = PaymentIntent::factory()
            ->forBooking($booking)
            ->pending()
            ->create();

        app(SimulatePaymentSuccessAction::class)($paymentIntent, $client);

        $this->assertDatabaseHas('video_sessions', [
            'booking_id' => $booking->id,
            'status' => VideoSessionStatus::Scheduled->value,
        ]);
        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'status' => BookingStatus::Paid->value,
        ]);
    }

    public function test_in_person_payment_does_not_create_video_session(): void
    {
        Event::fake([PaymentSucceeded::class]);
        [$booking, $client] = $this->bookingScenario([
            'status' => BookingStatus::Confirmed,
            'modality' => 'presencial',
        ]);
        $paymentIntent = PaymentIntent::factory()
            ->forBooking($booking)
            ->pending()
            ->create();

        app(SimulatePaymentSuccessAction::class)($paymentIntent, $client);

        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'status' => BookingStatus::Paid->value,
        ]);
        $this->assertDatabaseMissing('video_sessions', [
            'booking_id' => $booking->id,
        ]);
    }

    public function test_cancel_booking_cancels_video_session(): void
    {
        [$booking, $client] = $this->bookingScenario([
            'status' => BookingStatus::Paid,
            'paid_at' => now(),
        ]);
        $videoSession = app(EnsureVideoSessionForBookingAction::class)($booking);

        app(CancelBookingAction::class)($booking, $client);

        $this->assertDatabaseHas('video_sessions', [
            'id' => $videoSession->id,
            'status' => VideoSessionStatus::Cancelled->value,
        ]);
    }

    public function test_end_video_session_marks_session_as_ended(): void
    {
        [$booking, , $professionalUser] = $this->bookingScenario([
            'status' => BookingStatus::Paid,
            'paid_at' => now(),
        ]);
        $videoSession = app(EnsureVideoSessionForBookingAction::class)($booking);

        $ended = app(EndVideoSessionAction::class)($videoSession, $professionalUser);

        $this->assertSame(VideoSessionStatus::Ended, $ended->status);
        $this->assertNotNull($ended->ended_at);
    }

    private function bookingScenario(array $overrides = []): array
    {
        $professionalUser = User::factory()->professional()->create();
        $professionalProfile = ProfessionalProfile::factory()->create([
            'user_id' => $professionalUser->id,
        ]);
        $client = User::factory()->create();
        $modality = $overrides['modality'] ?? 'remota';
        $service = Service::factory()->create([
            'professional_id' => $professionalProfile->id,
            'modality' => $modality,
            'duration_minutes' => 60,
            'min_reschedule_minutes' => 10,
        ]);
        $status = $overrides['status'] ?? BookingStatus::Confirmed;
        $booking = Booking::factory()->create([
            'service_id' => $service->id,
            'professional_id' => $professionalProfile->id,
            'client_id' => $client->id,
            'starts_at' => '2026-06-15 09:00:00',
            'ends_at' => '2026-06-15 10:00:00',
            'status' => $status,
            'confirmed_at' => $status === BookingStatus::Pending ? null : now(),
            'modality' => $modality,
            'price_snapshot' => $service->price,
            'duration_minutes_snapshot' => 60,
            ...$overrides,
        ]);

        return [$booking, $client, $professionalUser, $professionalProfile, $service];
    }
}
