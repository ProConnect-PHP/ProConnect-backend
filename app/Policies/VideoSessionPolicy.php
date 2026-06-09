<?php

namespace App\Policies;

use App\Models\User\User;
use App\Models\Video\VideoSession;

class VideoSessionPolicy
{
    public function view(User $user, VideoSession $videoSession): bool
    {
        return $videoSession->client_id === $user->id
            || $user->professionalProfile?->id === $videoSession->professional_id;
    }

    public function join(User $user, VideoSession $videoSession): bool
    {
        return $this->view($user, $videoSession);
    }

    public function end(User $user, VideoSession $videoSession): bool
    {
        return $user->professionalProfile?->id === $videoSession->professional_id;
    }
}
