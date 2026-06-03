<?php

namespace Tests\Feature\Review;

use App\Actions\Review\RecalculateProfessionalRatingAction;
use App\Models\Booking\Booking;
use App\Models\Review\Review;
use App\Models\Service\Service;
use App\Models\User\ProfessionalProfile;
use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewRatingStatsTest extends TestCase
{
    use RefreshDatabase;

    public function test_average_rating_is_calculated_correctly(): void
    {
        [$profile, $service] = $this->professionalService();

        $this->createReview($profile, $service, 5);
        $this->createReview($profile, $service, 3);
        $this->createReview($profile, $service, 4);

        app(RecalculateProfessionalRatingAction::class)($profile);

        $profile->refresh();

        $this->assertSame(4.0, $profile->avg_rating);
        $this->assertSame(3, $profile->reviews_count);
    }

    public function test_editing_rating_recalculates_average(): void
    {
        [$profile, $service] = $this->professionalService();
        $review = $this->createReview($profile, $service, 5);
        $this->createReview($profile, $service, 3);

        app(RecalculateProfessionalRatingAction::class)($profile);

        $client = $review->client;
        $this
            ->withHeaders($this->authHeaders($client))
            ->putJson("/api/v1/reviews/{$review->id}", [
                'rating' => 1,
            ])
            ->assertOk();

        $profile->refresh();

        $this->assertSame(2.0, $profile->avg_rating);
        $this->assertSame(2, $profile->reviews_count);
    }

    public function test_deleting_comment_does_not_change_average(): void
    {
        [$profile, $service] = $this->professionalService();
        $review = $this->createReview($profile, $service, 5);

        app(RecalculateProfessionalRatingAction::class)($profile);

        $this
            ->withHeaders($this->authHeaders($review->client))
            ->deleteJson("/api/v1/reviews/{$review->id}")
            ->assertOk();

        $profile->refresh();

        $this->assertSame(5.0, $profile->avg_rating);
        $this->assertSame(1, $profile->reviews_count);
    }

    public function test_soft_deleted_reviews_do_not_count(): void
    {
        [$profile, $service] = $this->professionalService();
        $this->createReview($profile, $service, 5);
        $deleted = $this->createReview($profile, $service, 1);
        $deleted->delete();

        app(RecalculateProfessionalRatingAction::class)($profile);

        $profile->refresh();

        $this->assertSame(5.0, $profile->avg_rating);
        $this->assertSame(1, $profile->reviews_count);
    }

    private function professionalService(): array
    {
        $professionalUser = User::factory()->professional()->create();
        $profile = ProfessionalProfile::factory()->create([
            'user_id' => $professionalUser->id,
        ]);
        $service = Service::factory()->create([
            'professional_id' => $profile->id,
        ]);

        return [$profile, $service];
    }

    private function createReview(ProfessionalProfile $profile, Service $service, int $rating): Review
    {
        $client = User::factory()->create();
        $booking = Booking::factory()->completed()->create([
            'service_id' => $service->id,
            'professional_id' => $profile->id,
            'client_id' => $client->id,
            'modality' => $service->modality,
            'price_snapshot' => $service->price,
            'duration_minutes_snapshot' => $service->duration_minutes,
        ]);

        return Review::factory()->create([
            'booking_id' => $booking->id,
            'service_id' => $service->id,
            'professional_id' => $profile->id,
            'client_id' => $client->id,
            'rating' => $rating,
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
