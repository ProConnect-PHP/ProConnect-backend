<?php

namespace App\Actions\Video;

use App\Enums\Video\VideoSessionStatus;
use App\Models\Booking\Booking;

class CancelVideoSessionAction
{
    public function __invoke(Booking $booking): void
    {
        $videoSession = $booking->videoSession()
            ->lockForUpdate()
            ->first();

        if (! $videoSession || $videoSession->hasEnded()) {
            return;
        }

        $videoSession->update([
            'status' => VideoSessionStatus::Cancelled,
            'cancelled_at' => now(),
        ]);
    }
}
