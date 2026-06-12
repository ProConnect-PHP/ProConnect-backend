<?php

namespace App\Actions\Review;

use App\Exceptions\ApiException;
use App\Models\Review\Review;
use App\Models\User\User;
use App\Support\ActivityLog\ActivityLogActorMode;
use App\Support\ActivityLog\ActivityLogEvent;
use App\Support\ActivityLog\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class DeleteReviewCommentAction
{
    public function __construct(
        private readonly ActivityLogger $activityLogger,
    ) {}

    public function __invoke(Review $review, User $user): Review
    {
        $review = DB::transaction(function () use ($review, $user) {
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

        $this->activityLogger->record(
            event: ActivityLogEvent::ReviewDeleted,
            entityType: 'review',
            entityId: $review->id,
            entityOwnerId: $review->client_id,
            metadata: [
                'review_id' => $review->id,
                'service_id' => $review->service_id,
                'booking_id' => $review->booking_id,
                'comment_deleted_only' => true,
                'rating_preserved' => $review->rating,
            ],
            actor: $user,
            actingAs: ActivityLogActorMode::Client,
        );

        return $review;
    }
}
