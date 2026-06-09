<?php

namespace App\Actions\Video;

use App\Enums\Video\VideoSessionStatus;
use App\Exceptions\ApiException;
use App\Events\Video\VideoSessionJoined;
use App\Models\User\User;
use App\Models\Video\VideoSession;
use App\Models\Video\VideoSessionParticipant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class JoinVideoSessionAction
{
    public function __invoke(VideoSession $videoSession, User $user): array
    {
        return DB::transaction(function () use ($videoSession, $user) {
            $videoSession = VideoSession::query()
                ->with(['booking', 'client', 'professional.user'])
                ->whereKey($videoSession->id)
                ->lockForUpdate()
                ->firstOrFail();

            $isClient = $videoSession->client_id === $user->id;
            $isProfessional = $user->professionalProfile?->id === $videoSession->professional_id;

            if (! $isClient && ! $isProfessional) {
                throw new ApiException(
                    error: 'Forbidden',
                    message: 'No puedes unirte a esta sesion.',
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

            if (! $videoSession->isJoinWindowOpen()) {
                throw new ApiException(
                    error: 'VideoSessionJoinWindowClosed',
                    message: 'Todavia no puedes unirte a esta sesion o la ventana ya finalizo.',
                    status: Response::HTTP_CONFLICT
                );
            }

            $now = now();
            $role = $isProfessional ? 'professional' : 'client';
            $participant = VideoSessionParticipant::query()
                ->where('video_session_id', $videoSession->id)
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->first();

            if (! $participant) {
                $participant = new VideoSessionParticipant([
                    'video_session_id' => $videoSession->id,
                    'user_id' => $user->id,
                    'join_count' => 0,
                ]);
            }

            $participant->fill([
                'role' => $role,
                'provider_identity' => $role.'-'.$user->id,
                'display_name' => $user->name,
                'first_joined_at' => $participant->first_joined_at ?? $now,
                'last_joined_at' => $now,
                'left_at' => null,
                'join_count' => $participant->join_count + 1,
                'metadata' => [
                    'provider' => $videoSession->provider?->value ?? $videoSession->provider,
                ],
            ]);
            $participant->save();

            if ($videoSession->status === VideoSessionStatus::Scheduled) {
                $videoSession->update([
                    'status' => VideoSessionStatus::InProgress,
                    'started_at' => $videoSession->started_at ?? $now,
                    'opened_at' => $videoSession->opened_at ?? $now,
                ]);
            } elseif ($videoSession->opened_at === null) {
                $videoSession->update([
                    'opened_at' => $now,
                ]);
            }

            DB::afterCommit(function () use ($videoSession, $participant): void {
                event(new VideoSessionJoined($videoSession->id, $participant->id));
            });

            return [
                'video_session' => $videoSession->refresh(),
                'participant' => $participant->refresh(),
                'join_url' => $videoSession->join_url,
                'access_token' => 'sim_'.Str::uuid()->toString(),
                'expires_at' => $now->copy()->addMinutes(60),
            ];
        });
    }
}
