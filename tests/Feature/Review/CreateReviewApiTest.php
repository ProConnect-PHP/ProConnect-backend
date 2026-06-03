<?php

namespace Tests\Feature\Review;

use App\Enums\Booking\BookingStatus;
use App\Models\Booking\Booking;
use App\Models\Review\Review;
use App\Models\Service\Service;
use App\Models\User\ProfessionalProfile;
use App\Models\User\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateReviewApiTest extends TestCase
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

    public function test_guest_cannot_review_booking(): void
    {
        [$booking] = $this->completedBookingScenario();

        $response = $this->postJson("/api/v1/bookings/{$booking->id}/review", [
            'rating' => 5,
        ]);

        $response
            ->assertUnauthorized()
            ->assertJsonPath('error.type', 'Unauthorized');
    }

    public function test_client_can_review_own_completed_booking(): void
    {
        [$booking, $client] = $this->completedBookingScenario();

        $response = $this
            ->withHeaders($this->authHeaders($client))
            ->postJson("/api/v1/bookings/{$booking->id}/review", [
                'rating' => 5,
                'comment' => 'Excelente atencion.',
            ]);

        $response
            ->assertCreated()
            ->assertJsonPath('message', 'Reseña creada correctamente')
            ->assertJsonPath('review.rating', 5)
            ->assertJsonPath('review.comment', 'Excelente atencion.');

        $this->assertDatabaseHas('reviews', [
            'booking_id' => $booking->id,
            'client_id' => $client->id,
            'rating' => 5,
            'comment' => 'Excelente atencion.',
        ]);
    }

    public function test_cannot_review_pending_booking(): void
    {
        [$booking, $client] = $this->bookingScenario([
            'status' => BookingStatus::Pending,
        ]);

        $response = $this
            ->withHeaders($this->authHeaders($client))
            ->postJson("/api/v1/bookings/{$booking->id}/review", [
                'rating' => 5,
            ]);

        $response
            ->assertConflict()
            ->assertJsonPath('error.type', 'BookingNotCompleted');
    }

    public function test_cannot_review_confirmed_booking(): void
    {
        [$booking, $client] = $this->bookingScenario([
            'status' => BookingStatus::Confirmed,
            'confirmed_at' => now(),
        ]);

        $response = $this
            ->withHeaders($this->authHeaders($client))
            ->postJson("/api/v1/bookings/{$booking->id}/review", [
                'rating' => 5,
            ]);

        $response
            ->assertConflict()
            ->assertJsonPath('error.type', 'BookingNotCompleted');
    }

    public function test_cannot_review_another_clients_booking(): void
    {
        [$booking] = $this->completedBookingScenario();
        $stranger = User::factory()->create();

        $response = $this
            ->withHeaders($this->authHeaders($stranger))
            ->postJson("/api/v1/bookings/{$booking->id}/review", [
                'rating' => 5,
            ]);

        $response
            ->assertForbidden()
            ->assertJsonPath('error.type', 'Forbidden');
    }

    public function test_cannot_review_same_booking_twice(): void
    {
        [$booking, $client] = $this->completedBookingScenario();
        $this->createReview($booking, $client);

        $response = $this
            ->withHeaders($this->authHeaders($client))
            ->postJson("/api/v1/bookings/{$booking->id}/review", [
                'rating' => 4,
            ]);

        $response
            ->assertConflict()
            ->assertJsonPath('error.type', 'BookingAlreadyReviewed');
    }

    public function test_rating_must_be_at_least_one(): void
    {
        [$booking, $client] = $this->completedBookingScenario();

        $response = $this
            ->withHeaders($this->authHeaders($client))
            ->postJson("/api/v1/bookings/{$booking->id}/review", [
                'rating' => 0,
            ]);

        $response
            ->assertUnprocessable()
            ->assertJsonPath('error.type', 'ValidationError');
    }

    public function test_rating_must_be_at_most_five(): void
    {
        [$booking, $client] = $this->completedBookingScenario();

        $response = $this
            ->withHeaders($this->authHeaders($client))
            ->postJson("/api/v1/bookings/{$booking->id}/review", [
                'rating' => 6,
            ]);

        $response
            ->assertUnprocessable()
            ->assertJsonPath('error.type', 'ValidationError');
    }

    public function test_creating_review_recalculates_professional_rating_stats(): void
    {
        [$booking, $client, $profile] = $this->completedBookingScenario();

        $this
            ->withHeaders($this->authHeaders($client))
            ->postJson("/api/v1/bookings/{$booking->id}/review", [
                'rating' => 5,
            ])
            ->assertCreated();

        $profile->refresh();

        $this->assertSame(5.0, $profile->avg_rating);
        $this->assertSame(1, $profile->reviews_count);
    }

    private function completedBookingScenario(array $overrides = []): array
    {
        return $this->bookingScenario([
            'status' => BookingStatus::Completed,
            'completed_at' => now(),
            ...$overrides,
        ]);
    }

    private function bookingScenario(array $overrides = []): array
    {
        $professionalUser = User::factory()->professional()->create();
        $profile = ProfessionalProfile::factory()->create([
            'user_id' => $professionalUser->id,
        ]);
        $client = User::factory()->create();
        $service = Service::factory()->create([
            'professional_id' => $profile->id,
        ]);
        $booking = Booking::factory()->create([
            'service_id' => $service->id,
            'professional_id' => $profile->id,
            'client_id' => $client->id,
            'status' => BookingStatus::Completed,
            'completed_at' => now(),
            'modality' => $service->modality,
            'price_snapshot' => $service->price,
            'duration_minutes_snapshot' => $service->duration_minutes,
            ...$overrides,
        ]);

        return [$booking, $client, $profile, $professionalUser, $service];
    }

    private function createReview(Booking $booking, User $client, array $overrides = []): Review
    {
        return Review::factory()->create([
            'booking_id' => $booking->id,
            'service_id' => $booking->service_id,
            'professional_id' => $booking->professional_id,
            'client_id' => $client->id,
            'rating' => 5,
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
