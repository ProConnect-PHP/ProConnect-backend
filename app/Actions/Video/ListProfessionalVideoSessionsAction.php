<?php

namespace App\Actions\Video;

use App\Models\User\ProfessionalProfile;
use App\Models\Video\VideoSession;
use Illuminate\Pagination\LengthAwarePaginator;

class ListProfessionalVideoSessionsAction
{
    public function __invoke(ProfessionalProfile $professionalProfile, int $perPage = 10): LengthAwarePaginator
    {
        return VideoSession::query()
            ->with([
                'booking.service',
                'booking.payment',
                'booking.packageSession',
                'client',
                'participants',
            ])
            ->whereHas(
                'booking',
                fn ($booking) => $booking->paymentEntitled()
            )
            ->where('professional_id', $professionalProfile->id)
            ->latest('scheduled_start_at')
            ->paginate(min($perPage, 50));
    }
}
