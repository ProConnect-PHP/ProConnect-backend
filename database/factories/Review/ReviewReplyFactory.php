<?php

namespace Database\Factories\Review;

use App\Models\Review\Review;
use App\Models\Review\ReviewReply;
use App\Models\User\ProfessionalProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReviewReply>
 */
class ReviewReplyFactory extends Factory
{
    protected $model = ReviewReply::class;

    public function definition(): array
    {
        return [
            'review_id' => Review::factory(),
            'professional_id' => ProfessionalProfile::factory(),
            'body' => fake()->paragraph(),
            'edited_at' => null,
        ];
    }
}
