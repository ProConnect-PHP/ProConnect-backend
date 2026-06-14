<?php

namespace App\Actions\Video;

use App\Models\User\User;
use App\Models\Video\VideoSession;
use Illuminate\Pagination\LengthAwarePaginator;

class ListMyVideoSessionsAction
{
    public function __invoke(User $client, int $perPage = 10): LengthAwarePaginator
    {
        return VideoSession::query()
            ->with([
                'booking.service',
                'booking.payment',
                'booking.packageSession',
                'participants',
            ])
            ->whereHas(
                'booking',
                fn ($booking) => $booking->paymentEntitled()
            )
            ->where('client_id', $client->id)
            ->latest('scheduled_start_at')
            ->paginate(min($perPage, 50));
    }
}
