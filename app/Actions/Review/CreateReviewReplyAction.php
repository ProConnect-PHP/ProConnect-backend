<?php

namespace App\Actions\Review;

use App\Exceptions\ApiException;
use App\Models\Review\Review;
use App\Models\Review\ReviewReply;
use App\Models\User\ProfessionalProfile;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class CreateReviewReplyAction
{
    public function __invoke(
        Review $review,
        ProfessionalProfile $professionalProfile,
        array $data
    ): ReviewReply {
        return DB::transaction(function () use ($review, $professionalProfile, $data) {
            $review = Review::query()
                ->whereKey($review->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($review->professional_id !== $professionalProfile->id) {
                throw new ApiException(
                    error: 'Forbidden',
                    message: 'No puedes responder esta reseña.',
                    status: Response::HTTP_FORBIDDEN
                );
            }

            if ($review->reply()->exists()) {
                throw new ApiException(
                    error: 'ReviewAlreadyReplied',
                    message: 'Esta reseña ya tiene una respuesta profesional.',
                    status: Response::HTTP_CONFLICT
                );
            }

            return ReviewReply::create([
                'review_id' => $review->id,
                'professional_id' => $professionalProfile->id,
                'body' => $data['body'],
            ])->load('professional.user');
        });
    }
}
