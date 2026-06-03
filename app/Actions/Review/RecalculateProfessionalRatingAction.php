<?php

namespace App\Actions\Review;

use App\Models\Review\Review;
use App\Models\User\ProfessionalProfile;

class RecalculateProfessionalRatingAction
{
    public function __invoke(ProfessionalProfile $professionalProfile): void
    {
        $stats = Review::query()
            ->where('professional_id', $professionalProfile->id)
            ->selectRaw('COALESCE(AVG(rating), 0) as avg_rating, COUNT(*) as reviews_count')
            ->first();

        $professionalProfile->update([
            'avg_rating' => round((float) $stats->avg_rating, 2),
            'reviews_count' => (int) $stats->reviews_count,
        ]);
    }
}
