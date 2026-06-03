<?php

namespace App\Actions\Review;

use App\Exceptions\ApiException;
use App\Models\Review\Review;
use App\Models\User\User;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class DeleteReviewCommentAction
{
    public function __invoke(Review $review, User $user): Review
    {
        return DB::transaction(function () use ($review, $user) {
            $review = Review::query()
                ->whereKey($review->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($review->client_id !== $user->id) {
                throw new ApiException(
                    error: 'Forbidden',
                    message: 'No puedes modificar esta reseña.',
                    status: Response::HTTP_FORBIDDEN
                );
            }

            if (! $review->canCommentBeDeleted()) {
                throw new ApiException(
                    error: 'ReviewEditWindowExpired',
                    message: 'Ya no es posible modificar esta reseña.',
                    status: Response::HTTP_CONFLICT
                );
            }

            $review->update([
                'comment' => null,
                'comment_deleted_at' => now(),
                'edited_at' => now(),
            ]);

            return $review->refresh()->load(['client', 'reply.professional.user']);
        });
    }
}
