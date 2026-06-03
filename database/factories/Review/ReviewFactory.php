<?php

namespace Database\Factories\Review;

use App\Models\Booking\Booking;
use App\Models\Review\Review;
use App\Models\Service\Service;
use App\Models\User\ProfessionalProfile;
use App\Models\User\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Review>
 */
class ReviewFactory extends Factory
{
    protected $model = Review::class;

    public function definition(): array
    {
        return [
            'booking_id' => Booking::factory()->completed(),
            'service_id' => Service::factory(),
            'professional_id' => ProfessionalProfile::factory(),
            'client_id' => User::factory(),
            'rating' => fake()->numberBetween(1, 5),
            'comment' => fake()->paragraph(),
            'edited_at' => null,
            'comment_deleted_at' => null,
        ];
    }

    public function rating(int $rating): static
    {
        return $this->state(fn () => [
            'rating' => $rating,
        ]);
    }

    public function old(): static
    {
        return $this->state(fn () => [
            'created_at' => now()->subDays(10),
            'updated_at' => now()->subDays(10),
        ]);
    }
}
