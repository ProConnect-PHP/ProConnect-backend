<?php

namespace Tests\Feature\Review;

use App\Actions\Review\RecalculateProfessionalRatingAction;
use App\Enums\Booking\BookingStatus;
use App\Models\Booking\Booking;
use App\Models\Review\Review;
use App\Models\Service\Service;
use App\Models\User\ProfessionalProfile;
use App\Models\User\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewManagementApiTest extends TestCase
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

    public function test_client_author_can_update_review_inside_window(): void
    {
        [$review, $client, $profile] = $this->reviewScenario(['rating' => 5]);

        $response = $this
            ->withHeaders($this->authHeaders($client))
            ->putJson("/api/v1/reviews/{$review->id}", [
                'rating' => 4,
                'comment' => 'Actualizado.',
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('review.rating', 4)
            ->assertJsonPath('review.comment', 'Actualizado.')
            ->assertJsonPath('review.edited_at', '2026-06-01 12:00:00');

        $profile->refresh();

        $this->assertSame(4.0, $profile->avg_rating);
        $this->assertSame(1, $profile->reviews_count);
    }

    public function test_stranger_cannot_update_review(): void
    {
        [$review] = $this->reviewScenario();
        $stranger = User::factory()->create();

        $response = $this
            ->withHeaders($this->authHeaders($stranger))
            ->putJson("/api/v1/reviews/{$review->id}", [
                'rating' => 4,
            ]);

        $response
            ->assertForbidden()
            ->assertJsonPath('error.type', 'Forbidden');
    }

    public function test_cannot_update_review_outside_edit_window(): void
    {
        [$review, $client] = $this->reviewScenario([
            'created_at' => now()->subDays(10),
            'updated_at' => now()->subDays(10),
        ]);

        $response = $this
            ->withHeaders($this->authHeaders($client))
            ->putJson("/api/v1/reviews/{$review->id}", [
                'rating' => 4,
            ]);

        $response
            ->assertConflict()
            ->assertJsonPath('error.type', 'ReviewEditWindowExpired');
    }

    public function test_client_can_delete_comment_inside_window_without_removing_rating(): void
    {
        [$review, $client, $profile] = $this->reviewScenario([
            'rating' => 5,
            'comment' => 'Comentario original.',
        ]);

        $response = $this
            ->withHeaders($this->authHeaders($client))
            ->deleteJson("/api/v1/reviews/{$review->id}");

        $response
            ->assertOk()
            ->assertJsonPath('review.rating', 5)
            ->assertJsonPath('review.comment', null)
            ->assertJsonPath('review.comment_deleted_at', '2026-06-01 12:00:00');

        $review->refresh();
        $profile->refresh();

        $this->assertNull($review->comment);
        $this->assertNotNull($review->comment_deleted_at);
        $this->assertSame(5.0, $profile->avg_rating);
        $this->assertSame(1, $profile->reviews_count);
    }

    public function test_cannot_delete_comment_outside_window(): void
    {
        [$review, $client] = $this->reviewScenario([
            'created_at' => now()->subDays(10),
            'updated_at' => now()->subDays(10),
        ]);

        $response = $this
            ->withHeaders($this->authHeaders($client))
            ->deleteJson("/api/v1/reviews/{$review->id}");

        $response
            ->assertConflict()
            ->assertJsonPath('error.type', 'ReviewEditWindowExpired');
    }

    private function reviewScenario(array $reviewOverrides = []): array
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
            'rating' => 5,
            'comment' => 'Comentario original.',
            ...$reviewOverrides,
        ]);

        app(RecalculateProfessionalRatingAction::class)($profile);

        return [$review, $client, $profile, $professionalUser, $service];
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
