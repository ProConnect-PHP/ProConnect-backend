<?php

namespace App\Actions\Video;

use App\Enums\Video\VideoSessionStatus;
use App\Exceptions\ApiException;
use App\Models\User\User;
use App\Models\Video\VideoSession;
use App\Support\ActivityLog\ActivityLogActorMode;
use App\Support\ActivityLog\ActivityLogEvent;
use App\Support\ActivityLog\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class StartVideoSessionAction
{
    public function __construct(
        private readonly ActivityLogger $activityLogger,
    ) {}

    public function __invoke(VideoSession $videoSession, User $user): VideoSession
    {
        $videoSession = DB::transaction(function () use ($videoSession, $user) {
            $videoSession = VideoSession::query()
                ->whereKey($videoSession->id)
                ->lockForUpdate()
                ->firstOrFail();

            $isClient = $videoSession->client_id === $user->id;
            $isProfessional = $user->professionalProfile?->id === $videoSession->professional_id;

            if (! $isClient && ! $isProfessional) {
                throw new ApiException(
                    error: 'Forbidden',
                    message: 'No puedes iniciar esta sesion.',
                    status: Response::HTTP_FORBIDDEN
                );
            }

            if ($videoSession->isCancelled()) {
                throw new ApiException(
                    error: 'VideoSessionCancelled',
                    message: 'Esta sesion virtual fue cancelada.',
                    status: Response::HTTP_CONFLICT
                );
            }

            if ($videoSession->hasEnded()) {
                throw new ApiException(
                    error: 'VideoSessionEnded',
                    message: 'Esta sesion virtual ya finalizo.',
                    status: Response::HTTP_CONFLICT
                );
            }

            $now = now();
            $videoSession->update([
                'status' => VideoSessionStatus::InProgress,
                'started_at' => $videoSession->started_at ?? $now,
                'opened_at' => $videoSession->opened_at ?? $now,
            ]);

            return $videoSession->refresh()->load(['booking', 'participants']);
        });

        $this->activityLogger->record(
            event: ActivityLogEvent::VideoSessionStarted,
            entityType: 'video_session',
            entityId: $videoSession->id,
            entityOwnerId: $videoSession->professional_id,
            metadata: [
                'video_session_id' => $videoSession->id,
                'booking_id' => $videoSession->booking_id,
                'room_name' => $videoSession->room_name,
                'participant_user_id' => $user->id,
                'status' => $videoSession->status,
            ],
            actor: $user,
            actingAs: $user->professionalProfile?->id === $videoSession->professional_id
                ? ActivityLogActorMode::Professional
                : ActivityLogActorMode::Client,
        );

        return $videoSession;
    }
}
