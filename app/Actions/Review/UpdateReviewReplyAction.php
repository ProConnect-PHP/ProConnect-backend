<?php

namespace App\Actions\Review;

use App\Exceptions\ApiException;
use App\Models\Review\ReviewReply;
use App\Models\User\ProfessionalProfile;
use App\Support\ActivityLog\ActivityLogActorMode;
use App\Support\ActivityLog\ActivityLogEvent;
use App\Support\ActivityLog\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class UpdateReviewReplyAction
{
    public function __construct(
        private readonly ActivityLogger $activityLogger,
    ) {}

    public function __invoke(
        ReviewReply $reply,
        ProfessionalProfile $professionalProfile,
        array $data
    ): ReviewReply {
        $reply = DB::transaction(function () use ($reply, $professionalProfile, $data) {
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

        $this->activityLogger->record(
            event: ActivityLogEvent::ReviewReplyUpdated,
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
