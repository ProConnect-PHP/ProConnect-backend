<?php

namespace App\Actions\Review;

use App\Exceptions\ApiException;
use App\Models\Review\Review;
use App\Models\Review\ReviewReply;
use App\Models\User\ProfessionalProfile;
use App\Support\ActivityLog\ActivityLogActorMode;
use App\Support\ActivityLog\ActivityLogEvent;
use App\Support\ActivityLog\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class CreateReviewReplyAction
{
    public function __construct(
        private readonly ActivityLogger $activityLogger,
    ) {}

    public function __invoke(
        Review $review,
        ProfessionalProfile $professionalProfile,
        array $data
    ): ReviewReply {
        $reply = DB::transaction(function () use ($review, $professionalProfile, $data) {
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

        $this->activityLogger->record(
            event: ActivityLogEvent::ReviewReplyCreated,
            entityType: 'review_reply',
            entityId: $reply->id,
            entityOwnerId: $professionalProfile->id,
            metadata: [
                'review_reply_id' => $reply->id,
                'review_id' => $reply->review_id,
                'professional_id' => $reply->professional_id,
                'body_length' => mb_strlen((string) $data['body']),
            ],
            actingAs: ActivityLogActorMode::Professional,
        );

        return $reply;
    }
}
