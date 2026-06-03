<?php

namespace App\Actions\Review;

use App\Exceptions\ApiException;
use App\Models\Review\ReviewReply;
use App\Models\User\ProfessionalProfile;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class UpdateReviewReplyAction
{
    public function __invoke(
        ReviewReply $reply,
        ProfessionalProfile $professionalProfile,
        array $data
    ): ReviewReply {
        return DB::transaction(function () use ($reply, $professionalProfile, $data) {
            $reply = ReviewReply::query()
                ->whereKey($reply->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($reply->professional_id !== $professionalProfile->id) {
                throw new ApiException(
                    error: 'Forbidden',
                    message: 'No puedes modificar esta respuesta.',
                    status: Response::HTTP_FORBIDDEN
                );
            }

            $reply->update([
                'body' => $data['body'],
                'edited_at' => now(),
            ]);

            return $reply->refresh()->load('professional.user');
        });
    }
}
