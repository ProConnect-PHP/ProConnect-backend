<?php

namespace App\Policies;

use App\Models\Review\ReviewReply;
use App\Models\User\User;

class ReviewReplyPolicy
{
    public function update(User $user, ReviewReply $reply): bool
    {
        return $user->professionalProfile?->id === $reply->professional_id;
    }
}
