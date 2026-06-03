<?php

namespace Tests\Feature\Review;

use App\Models\Booking\Booking;
use App\Models\Review\Review;
use App\Models\Review\ReviewReply;
use App\Models\Service\Service;
use App\Models\User\ProfessionalProfile;
use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class ReviewPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_author_can_update_and_delete_review(): void
    {
        [$review, $client] = $this->reviewScenario();

        $this->assertTrue(Gate::forUser($client)->allows('update', $review));
        $this->assertTrue(Gate::forUser($client)->allows('delete', $review));
    }

    public function test_stranger_cannot_update_or_delete_review(): void
    {
        [$review] = $this->reviewScenario();
        $stranger = User::factory()->create();

        $this->assertFalse(Gate::forUser($stranger)->allows('update', $review));
        $this->assertFalse(Gate::forUser($stranger)->allows('delete', $review));
    }

    public function test_professional_owner_can_reply_review(): void
    {
        [$review,, $professionalUser] = $this->reviewScenario();

        $this->assertTrue(Gate::forUser($professionalUser)->allows('reply', $review));
    }

    public function test_other_professional_and_client_cannot_reply_review(): void
    {
        [$review, $client] = $this->reviewScenario();
        [$otherProfessionalUser] = $this->createProfessional();

        $this->assertFalse(Gate::forUser($otherProfessionalUser)->allows('reply', $review));
        $this->assertFalse(Gate::forUser($client)->allows('reply', $review));
    }

    public function test_professional_owner_can_update_reply(): void
    {
        [$review,, $professionalUser, $profile] = $this->reviewScenario();
        $reply = ReviewReply::factory()->create([
            'review_id' => $review->id,
            'professional_id' => $profile->id,
        ]);

        $this->assertTrue(Gate::forUser($professionalUser)->allows('update', $reply));
    }

    public function test_other_professional_cannot_update_reply(): void
    {
        [$review,,, $profile] = $this->reviewScenario();
        [$otherProfessionalUser] = $this->createProfessional();
        $reply = ReviewReply::factory()->create([
            'review_id' => $review->id,
            'professional_id' => $profile->id,
        ]);

        $this->assertFalse(Gate::forUser($otherProfessionalUser)->allows('update', $reply));
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
}
