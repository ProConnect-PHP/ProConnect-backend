<?php

namespace App\Events\Video;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class VideoSessionJoined
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly string $videoSessionId,
        public readonly string $participantId
    ) {
    }
}
