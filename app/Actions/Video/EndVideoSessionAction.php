<?php

namespace App\Actions\Video;

use App\Enums\Video\VideoSessionStatus;
use App\Exceptions\ApiException;
use App\Events\Video\VideoSessionEnded;
use App\Models\User\User;
use App\Models\Video\VideoSession;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class EndVideoSessionAction
{
    public function __invoke(VideoSession $videoSession, User $user): VideoSession
    {
        return DB::transaction(function () use ($videoSession, $user) {
            $videoSession = VideoSession::query()
                ->whereKey($videoSession->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($user->professionalProfile?->id !== $videoSession->professional_id) {
                throw new ApiException(
                    error: 'Forbidden',
                    message: 'No puedes finalizar esta sesion.',
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
                    error: 'VideoSessionAlreadyEnded',
                    message: 'Esta sesion virtual ya finalizo.',
                    status: Response::HTTP_CONFLICT
                );
            }

            $videoSession->update([
                'status' => VideoSessionStatus::Ended,
                'ended_at' => now(),
            ]);

            DB::afterCommit(function () use ($videoSession): void {
                event(new VideoSessionEnded($videoSession->id));
            });

            return $videoSession->refresh()->load(['booking', 'participants']);
        });
    }
}
