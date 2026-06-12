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

class UpdateReviewAction
{
    public function __construct(
        private readonly RecalculateProfessionalRatingAction $recalculateProfessionalRating,
        private readonly ActivityLogger $activityLogger,
    ) {}

    public function __invoke(Review $review, User $user, array $data): Review
    {
        $review = DB::transaction(function () use ($review, $user, $data) {
            $review = Review::query()
                ->with('professional')
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

            if (! $review->canBeEdited()) {
                throw new ApiException(
                    error: 'ReviewEditWindowExpired',
                    message: 'Ya no es posible modificar esta reseña.',
                    status: Response::HTTP_CONFLICT
                );
            }

            $ratingChanged = array_key_exists('rating', $data)
                && (int) $data['rating'] !== $review->rating;

            $payload = [
                'edited_at' => now(),
            ];

            if (array_key_exists('rating', $data)) {
                $payload['rating'] = $data['rating'];
            }

            if (array_key_exists('comment', $data)) {
                $payload['comment'] = $data['comment'];
                $payload['comment_deleted_at'] = null;
            }

            $review->update($payload);

            if ($ratingChanged) {
                ($this->recalculateProfessionalRating)($review->professional);
            }

            return $review->refresh()->load(['client', 'reply.professional.user']);
        });

        $this->activityLogger->record(
            event: ActivityLogEvent::ReviewUpdated,
            entityType: 'review',
            entityId: $review->id,
            entityOwnerId: $review->client_id,
            metadata: [
                'review_id' => $review->id,
                'service_id' => $review->service_id,
                'booking_id' => $review->booking_id,
                'professional_id' => $review->professional_id,
                'rating' => $review->rating,
                'changed_fields' => array_keys($data),
                'comment_length' => array_key_exists('comment', $data)
                    ? mb_strlen((string) $data['comment'])
                    : null,
            ],
            actor: $user,
            actingAs: ActivityLogActorMode::Client,
        );

        return $review;
    }
}
