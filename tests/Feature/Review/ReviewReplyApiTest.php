<?php

namespace Tests\Feature\Review;

use App\Models\Booking\Booking;
use App\Models\Review\Review;
use App\Models\Review\ReviewReply;
use App\Models\Service\Service;
use App\Models\User\ProfessionalProfile;
use App\Models\User\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewReplyApiTest extends TestCase
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

    public function test_professional_owner_can_reply_review(): void
    {
        [$review,, $professionalUser] = $this->reviewScenario();

        $response = $this
            ->withHeaders($this->authHeaders($professionalUser))
            ->postJson("/api/v1/reviews/{$review->id}/replies", [
                'body' => 'Muchas gracias por tu comentario.',
            ]);

        $response
            ->assertCreated()
            ->assertJsonPath('message', 'Respuesta creada correctamente')
            ->assertJsonPath('reply.body', 'Muchas gracias por tu comentario.');
    }

    public function test_other_professional_cannot_reply_review(): void
    {
        [$review] = $this->reviewScenario();
        [$otherProfessionalUser] = $this->createProfessional();

        $response = $this
            ->withHeaders($this->authHeaders($otherProfessionalUser))
            ->postJson("/api/v1/reviews/{$review->id}/replies", [
                'body' => 'Respuesta ajena.',
            ]);

        $response
            ->assertForbidden()
            ->assertJsonPath('error.type', 'Forbidden');
    }

    public function test_client_without_professional_profile_cannot_reply_review(): void
    {
        [$review, $client] = $this->reviewScenario();

        $response = $this
            ->withHeaders($this->authHeaders($client))
            ->postJson("/api/v1/reviews/{$review->id}/replies", [
                'body' => 'No deberia responder.',
            ]);

        $response
            ->assertForbidden()
            ->assertJsonPath('error.type', 'Forbidden');
    }

    public function test_cannot_reply_same_review_twice(): void
    {
        [$review,, $professionalUser, $profile] = $this->reviewScenario();
        ReviewReply::factory()->create([
            'review_id' => $review->id,
            'professional_id' => $profile->id,
        ]);

        $response = $this
            ->withHeaders($this->authHeaders($professionalUser))
            ->postJson("/api/v1/reviews/{$review->id}/replies", [
                'body' => 'Segunda respuesta.',
            ]);

        $response
            ->assertConflict()
            ->assertJsonPath('error.type', 'ReviewAlreadyReplied');
    }

    public function test_professional_owner_can_update_reply(): void
    {
        [$review,, $professionalUser, $profile] = $this->reviewScenario();
        $reply = ReviewReply::factory()->create([
            'review_id' => $review->id,
            'professional_id' => $profile->id,
            'body' => 'Respuesta inicial.',
        ]);

        $response = $this
            ->withHeaders($this->authHeaders($professionalUser))
            ->putJson("/api/v1/review-replies/{$reply->id}", [
                'body' => 'Respuesta actualizada.',
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('reply.body', 'Respuesta actualizada.')
            ->assertJsonPath('reply.edited_at', '2026-06-01 12:00:00');
    }

    public function test_other_professional_cannot_update_reply(): void
    {
        [$review,,, $profile] = $this->reviewScenario();
        [$otherProfessionalUser] = $this->createProfessional();
        $reply = ReviewReply::factory()->create([
            'review_id' => $review->id,
            'professional_id' => $profile->id,
        ]);

        $response = $this
            ->withHeaders($this->authHeaders($otherProfessionalUser))
            ->putJson("/api/v1/review-replies/{$reply->id}", [
                'body' => 'Intento ajeno.',
            ]);

        $response
            ->assertForbidden()
            ->assertJsonPath('error.type', 'Forbidden');
    }

    private function reviewScenario(): array
    {
        [$professionalUser, $profile] = $this->createProfessional();
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
        ]);

        return [$review, $client, $professionalUser, $profile];
    }

    private function createProfessional(): array
    {
        $user = User::factory()->professional()->create();
        $profile = ProfessionalProfile::factory()->create([
            'user_id' => $user->id,
        ]);

        return [$user, $profile];
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
