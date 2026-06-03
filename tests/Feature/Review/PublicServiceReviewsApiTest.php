<?php

namespace Tests\Feature\Review;

use App\Models\Booking\Booking;
use App\Models\Review\Review;
use App\Models\Review\ReviewReply;
use App\Models\Service\Service;
use App\Models\User\ProfessionalProfile;
use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicServiceReviewsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_reviews_endpoint_does_not_require_authentication(): void
    {
        [$service] = $this->serviceWithReview();

        $response = $this->getJson("/api/v1/services/{$service->id}/reviews");

        $response
            ->assertOk()
            ->assertJsonCount(1, 'reviews');
    }

    public function test_lists_only_reviews_for_requested_service(): void
    {
        [$service, $review] = $this->serviceWithReview(comment: 'Review solicitada.');
        [$otherService] = $this->serviceWithReview(comment: 'Review ajena.');

        $response = $this->getJson("/api/v1/services/{$service->id}/reviews");

        $response
            ->assertOk()
            ->assertJsonCount(1, 'reviews')
            ->assertJsonPath('reviews.0.id', $review->id);

        $this->assertNotSame($otherService->id, $response->json('reviews.0.service_id'));
    }

    public function test_public_reviews_do_not_expose_client_email(): void
    {
        [$service] = $this->serviceWithReview();

        $response = $this->getJson("/api/v1/services/{$service->id}/reviews");

        $response
            ->assertOk()
            ->assertJsonMissingPath('reviews.0.client.email');
    }

    public function test_public_reviews_include_reply_when_present(): void
    {
        [$service, $review, $profile] = $this->serviceWithReview();
        ReviewReply::factory()->create([
            'review_id' => $review->id,
            'professional_id' => $profile->id,
            'body' => 'Gracias por la reseña.',
        ]);

        $response = $this->getJson("/api/v1/services/{$service->id}/reviews");

        $response
            ->assertOk()
            ->assertJsonPath('reviews.0.reply.body', 'Gracias por la reseña.')
            ->assertJsonMissingPath('reviews.0.reply.professional.user.email');
    }

    public function test_public_reviews_are_paginated(): void
    {
        [$service,,,, $client] = $this->serviceWithReview();
        $profile = $service->professional;

        foreach (range(1, 12) as $index) {
            $booking = Booking::factory()->completed()->create([
                'service_id' => $service->id,
                'professional_id' => $profile->id,
                'client_id' => User::factory()->create()->id,
                'modality' => $service->modality,
                'price_snapshot' => $service->price,
                'duration_minutes_snapshot' => $service->duration_minutes,
            ]);
            Review::factory()->create([
                'booking_id' => $booking->id,
                'service_id' => $service->id,
                'professional_id' => $profile->id,
                'client_id' => $booking->client_id,
                'comment' => "Review {$index}",
            ]);
        }

        $response = $this->getJson("/api/v1/services/{$service->id}/reviews?per_page=10");

        $response
            ->assertOk()
            ->assertJsonCount(10, 'reviews')
            ->assertJsonPath('meta.total', 13)
            ->assertJsonPath('meta.per_page', 10);

        $this->assertNotNull($client);
    }

    public function test_deleted_comment_is_public_as_null_but_rating_remains(): void
    {
        [$service, $review] = $this->serviceWithReview(comment: null, reviewOverrides: [
            'rating' => 4,
            'comment_deleted_at' => now(),
        ]);

        $response = $this->getJson("/api/v1/services/{$service->id}/reviews");

        $response
            ->assertOk()
            ->assertJsonPath('reviews.0.rating', 4)
            ->assertJsonPath('reviews.0.comment', null);
    }

    private function serviceWithReview(?string $comment = 'Excelente.', array $reviewOverrides = []): array
    {
        $professionalUser = User::factory()->professional()->create();
        $profile = ProfessionalProfile::factory()->create([
            'user_id' => $professionalUser->id,
        ]);
        $client = User::factory()->create();
        $service = Service::factory()->create([
            'professional_id' => $profile->id,
        ]);
        $booking = Booking::factory()->completed()->create([
            'service_id' => $service->id,
            'professional_id' => $profile->id,
            'client_id' => $client->id,
            'modality' => $service->modality,
            'price_snapshot' => $service->price,
            'duration_minutes_snapshot' => $service->duration_minutes,
        ]);
        $review = Review::factory()->create([
            'booking_id' => $booking->id,
            'service_id' => $service->id,
            'professional_id' => $profile->id,
            'client_id' => $client->id,
            'comment' => $comment,
            ...$reviewOverrides,
        ]);

        return [$service->load('professional'), $review, $profile, $professionalUser, $client];
    }
}
