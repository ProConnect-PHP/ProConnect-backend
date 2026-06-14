<?php

namespace App\Modules\VideoSession\Application\UseCases;

use App\Enums\Booking\BookingStatus;
use App\Enums\Video\VideoSessionStatus;
use App\Exceptions\ApiException;
use App\Models\Booking\Booking;
use App\Models\User\User;
use App\Modules\VideoSession\Application\DTO\JoinVideoSessionData;
use App\Modules\VideoSession\Domain\Enums\VideoParticipantRole;
use App\Modules\VideoSession\Infrastructure\LiveKit\LiveKitTokenService;
use App\Support\ActivityLog\ActivityLogActorMode;
use App\Support\ActivityLog\ActivityLogEvent;
use App\Support\ActivityLog\ActivityLogger;
use Illuminate\Auth\Access\AuthorizationException;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

final readonly class GenerateVideoSessionTokenUseCase
{
    public function __construct(
        private LiveKitTokenService $liveKitTokenService,
        private ActivityLogger $activityLogger,
    ) {}

    public function execute(Booking $booking, User $user): JoinVideoSessionData
    {
        $booking->loadMissing([
            'payment',
            'packageSession',
            'videoSession',
        ]);
        $participantRole = $this->resolveParticipantRole($booking, $user);
        $this->assertBookingAllowsVideoSession($booking);
        $videoSession = $booking->videoSession;

        if (! $videoSession) {
            throw new ApiException(
                error: 'VideoSessionNotScheduled',
                message: 'La sesion de video no esta programada.',
                status: Response::HTTP_NOT_FOUND,
            );
        }

        if (
            ! in_array($videoSession->status, [
                VideoSessionStatus::Scheduled,
                VideoSessionStatus::InProgress,
            ], true)
            || $videoSession->isCancelled()
            || $videoSession->hasEnded()
        ) {
            throw new ApiException(
                error: 'VideoSessionNotScheduled',
                message: 'La sesion de video no esta disponible.',
                status: Response::HTTP_NOT_FOUND,
            );
        }

        if (! $videoSession->isJoinWindowOpen()) {
            throw new ApiException(
                error: 'VideoSessionJoinWindowClosed',
                message: 'La videollamada todavia no esta disponible.',
                status: Response::HTTP_FORBIDDEN,
            );
        }

        $roomName = $videoSession->room_name;
        $participantIdentity = sprintf(
            'user_%s_booking_%s',
            $user->getKey(),
            $booking->getKey()
        );
        $participantName = filled($user->name) ? $user->name : 'Usuario';

        $joinData = new JoinVideoSessionData(
            url: $this->liveKitUrl(),
            token: $this->liveKitTokenService->generateJoinToken(
                roomName: $roomName,
                participantIdentity: $participantIdentity,
                participantName: $participantName,
                canPublish: true,
                canSubscribe: true,
            ),
            roomName: $roomName,
            participantIdentity: $participantIdentity,
            participantName: $participantName,
        );

        $this->activityLogger->record(
            event: ActivityLogEvent::VideoSessionTokenIssued,
            entityType: 'booking',
            entityId: $booking->id,
            entityOwnerId: $booking->professional_id,
            metadata: [
                'booking_id' => $booking->id,
                'room_name' => $roomName,
                'participant_user_id' => $user->id,
                'participant_identity' => $participantIdentity,
            ],
            actor: $user,
            actingAs: $participantRole === VideoParticipantRole::Professional
                ? ActivityLogActorMode::Professional
                : ActivityLogActorMode::Client,
        );

        return $joinData;
    }

    private function resolveParticipantRole(
        Booking $booking,
        User $user
    ): VideoParticipantRole {
        if ((string) $booking->client_id === (string) $user->getKey()) {
            return VideoParticipantRole::Client;
        }

        if (
            $user->professionalProfile
            && (string) $booking->professional_id
                === (string) $user->professionalProfile->getKey()
        ) {
            return VideoParticipantRole::Professional;
        }

        throw new AuthorizationException(
            'No tienes permisos para acceder a la videollamada de esta reserva.'
        );
    }

    private function assertBookingAllowsVideoSession(Booking $booking): void
    {
        if (! $booking->isPaymentEntitled()) {
            throw new ApiException(
                error: 'VideoSessionPaymentRequired',
                message: 'La reserva debe estar pagada o cubierta por un paquete para acceder a la videollamada.',
                status: Response::HTTP_FORBIDDEN,
            );
        }

        if (! in_array($booking->status, [
            BookingStatus::Confirmed,
            BookingStatus::Paid,
            BookingStatus::InProgress,
        ], true)) {
            throw new ApiException(
                error: 'BookingNotEligibleForVideoSession',
                message: 'La reserva no esta disponible para videollamada.',
                status: Response::HTTP_FORBIDDEN,
            );
        }

        if (! $booking->isVideoEligible()) {
            throw new ApiException(
                error: 'VideoSessionNotAllowedForModality',
                message: 'La modalidad de la reserva no permite videollamada.',
                status: Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }
    }

    private function liveKitUrl(): string
    {
        $url = config('services.livekit.url');

        if (! is_string($url) || trim($url) === '') {
            throw new RuntimeException('LIVEKIT_URL is not configured.');
        }

        return $url;
    }
}
