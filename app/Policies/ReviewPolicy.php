<?php

namespace App\Policies;

use App\Models\Review\Review;
use App\Models\User\User;

class ReviewPolicy
{
    public function update(User $user, Review $review): bool
    {
        return $review->client_id === $user->id;
    }

    public function delete(User $user, Review $review): bool
    {
        return $review->client_id === $user->id;
    }

    public function reply(User $user, Review $review): bool
    {
        return $user->professionalProfile?->id === $review->professional_id;
    }
}
