<?php

namespace Tests\Feature\Authorization;

use App\Actions\Video\EnsureVideoSessionForBookingAction;
use App\Enums\Booking\BookingStatus;
use App\Models\Availability\AvailabilityRule;
use App\Models\Booking\Booking;
use App\Models\Package\ClientPackage;
use App\Models\Package\PackageProduct;
use App\Models\Payment\Payment;
use App\Models\Payment\PaymentIntent;
use App\Models\Service\Service;
use App\Models\User\ProfessionalProfile;
use App\Models\User\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfessionalCanActAsClientTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-06-01 12:00:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_professional_can_list_own_client_bookings(): void
    {
        [$professional] = $this->createProfessional();
        [, $serviceOwner] = $this->createProfessional();

        $ownBooking = $this->createBooking($professional, $serviceOwner);
        $this->createBooking(User::factory()->create(), $serviceOwner);

        $this->getJson('/api/v1/bookings/my', $this->authHeaders($professional))
            ->assertOk()
            ->assertJsonCount(1, 'bookings')
            ->assertJsonPath('bookings.0.id', $ownBooking->id);
    }

    public function test_professional_can_create_booking_for_another_professionals_service(): void
    {
        [$professional] = $this->createProfessional();
        [, $serviceOwner] = $this->createProfessional();
        $service = $this->createBookableService($serviceOwner);

        $this->postJson("/api/v1/services/{$service->id}/bookings", [
            'starts_at' => '2026-06-15 09:00:00',
        ], $this->authHeaders($professional))
            ->assertCreated()
            ->assertJsonPath('booking.client_id', $professional->id);
    }

    public function test_professional_cannot_book_own_service(): void
    {
        [$professional, $profile] = $this->createProfessional();
        $service = $this->createBookableService($profile);

        $this->postJson("/api/v1/services/{$service->id}/bookings", [
            'starts_at' => '2026-06-15 09:00:00',
        ], $this->authHeaders($professional))
            ->assertForbidden()
            ->assertJsonPath('error.type', 'CannotBookOwnService');
    }

    public function test_professional_can_list_own_client_payments(): void
    {
        [$professional] = $this->createProfessional();
        [, $serviceOwner] = $this->createProfessional();
        $booking = $this->createBooking($professional, $serviceOwner, [
            'status' => BookingStatus::Paid,
            'paid_at' => now(),
        ]);
        $intent = PaymentIntent::factory()->forBooking($booking)->succeeded()->create();
        $payment = Payment::factory()->forPaymentIntent($intent)->succeeded()->create();

        $this->getJson('/api/v1/payments/my', $this->authHeaders($professional))
            ->assertOk()
            ->assertJsonCount(1, 'payments')
            ->assertJsonPath('payments.0.id', $payment->id);
    }

    public function test_professional_can_list_own_client_packages(): void
    {
        [$professional] = $this->createProfessional();
        [, $serviceOwner] = $this->createProfessional();
        $service = $this->createService($serviceOwner);
        $product = PackageProduct::factory()->forService($service)->active()->create();
        $clientPackage = ClientPackage::factory()->forPackageProduct($product)->create([
            'client_id' => $professional->id,
        ]);

        $this->getJson('/api/v1/client-packages/my', $this->authHeaders($professional))
            ->assertOk()
            ->assertJsonCount(1, 'client_packages')
            ->assertJsonPath('client_packages.0.id', $clientPackage->id);
    }

    public function test_professional_can_list_own_client_video_sessions(): void
    {
        [$professional] = $this->createProfessional();
        [, $serviceOwner] = $this->createProfessional();
        $booking = $this->createBooking($professional, $serviceOwner, [
            'status' => BookingStatus::Paid,
            'paid_at' => now(),
        ]);
        $videoSession = app(EnsureVideoSessionForBookingAction::class)($booking);

        $this->getJson('/api/v1/video-sessions/my', $this->authHeaders($professional))
            ->assertOk()
            ->assertJsonCount(1, 'video_sessions')
            ->assertJsonPath('video_sessions.0.id', $videoSession->id);
    }

    public function test_professional_can_purchase_another_professionals_package(): void
    {
        [$professional] = $this->createProfessional();
        [, $serviceOwner] = $this->createProfessional();
        $service = $this->createService($serviceOwner);
        $product = PackageProduct::factory()->forService($service)->active()->create();

        $this->postJson(
            "/api/v1/package-products/{$product->id}/purchase",
            [],
            $this->authHeaders($professional),
        )
            ->assertOk()
            ->assertJsonPath('client_package.client_id', $professional->id);
    }

    public function test_professional_cannot_purchase_own_package(): void
    {
        [$professional, $profile] = $this->createProfessional();
        $service = $this->createService($profile);
        $product = PackageProduct::factory()->forService($service)->active()->create();

        $this->postJson(
            "/api/v1/package-products/{$product->id}/purchase",
            [],
            $this->authHeaders($professional),
        )
            ->assertConflict()
            ->assertJsonPath('error.type', 'CannotPurchaseOwnPackage');
    }

    public function test_professional_can_review_a_completed_booking_as_its_client(): void
    {
        [$professional] = $this->createProfessional();
        [, $serviceOwner] = $this->createProfessional();
        $booking = $this->createBooking($professional, $serviceOwner, [
            'status' => BookingStatus::Completed,
            'completed_at' => now(),
        ]);

        $this->postJson("/api/v1/bookings/{$booking->id}/review", [
            'rating' => 5,
            'comment' => 'Excelente atención.',
        ], $this->authHeaders($professional))
            ->assertCreated()
            ->assertJsonPath('review.client.id', $professional->id);
    }

    public function test_professional_cannot_review_own_service(): void
    {
        [$professional, $profile] = $this->createProfessional();
        $booking = $this->createBooking($professional, $profile, [
            'status' => BookingStatus::Completed,
            'completed_at' => now(),
        ]);

        $this->postJson("/api/v1/bookings/{$booking->id}/review", [
            'rating' => 5,
        ], $this->authHeaders($professional))
            ->assertForbidden()
            ->assertJsonPath('error.type', 'Forbidden');
    }

    public function test_client_cannot_access_professional_routes(): void
    {
        $client = User::factory()->create();

        $this->getJson('/api/v1/services/my', $this->authHeaders($client))
            ->assertForbidden();
    }

    public function test_professional_routes_still_require_professional_role(): void
    {
        [$professional] = $this->createProfessional();

        $this->getJson('/api/v1/services/my', $this->authHeaders($professional))
            ->assertOk();
    }

    /**
     * @return array{User, ProfessionalProfile}
     */
    private function createProfessional(): array
    {
        $user = User::factory()->professional()->create();
        $profile = ProfessionalProfile::factory()->create([
            'user_id' => $user->id,
        ]);

        return [$user, $profile];
    }

    private function createService(
        ProfessionalProfile $professional,
        array $attributes = [],
    ): Service {
        return Service::factory()
            ->active()
            ->create(array_merge([
                'professional_id' => $professional->id,
                'duration_minutes' => 60,
                'buffer_minutes' => 15,
                'modality' => 'remota',
            ], $attributes));
    }

    private function createBookableService(ProfessionalProfile $professional): Service
    {
        $service = $this->createService($professional);

        AvailabilityRule::factory()->create([
            'service_id' => $service->id,
            'day_of_week' => 1,
            'start_time' => '09:00:00',
            'end_time' => '12:00:00',
            'is_active' => true,
        ]);

        return $service;
    }

    private function createBooking(
        User $client,
        ProfessionalProfile $professional,
        array $attributes = [],
    ): Booking {
        $service = $this->createService($professional);

        return Booking::factory()->create(array_merge([
            'client_id' => $client->id,
            'professional_id' => $professional->id,
            'service_id' => $service->id,
        ], $attributes));
    }

    /**
     * @return array<string, string>
     */
    private function authHeaders(User $user): array
    {
        $token = auth('user_jwt')->login($user);

        return [
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ];
    }
}
