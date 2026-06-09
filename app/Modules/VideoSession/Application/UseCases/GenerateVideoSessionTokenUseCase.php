<?php

namespace App\Modules\VideoSession\Application\UseCases;

use App\Enums\Booking\BookingStatus;
use App\Models\Booking\Booking;
use App\Models\User\User;
use App\Modules\VideoSession\Application\DTO\JoinVideoSessionData;
use App\Modules\VideoSession\Domain\Enums\VideoParticipantRole;
use App\Modules\VideoSession\Domain\Services\VideoRoomNameGenerator;
use App\Modules\VideoSession\Infrastructure\LiveKit\LiveKitTokenService;
use Illuminate\Auth\Access\AuthorizationException;
use RuntimeException;

final readonly class GenerateVideoSessionTokenUseCase
{
    public function __construct(
        private LiveKitTokenService $liveKitTokenService,
        private VideoRoomNameGenerator $roomNameGenerator,
    ) {}

    public function execute(Booking $booking, User $user): JoinVideoSessionData
    {
        $this->resolveParticipantRole($booking, $user);
        $this->assertBookingAllowsVideoSession($booking);

        $roomName = $this->roomNameGenerator->forBooking($booking);
        $participantIdentity = sprintf(
            'user_%s_booking_%s',
            $user->getKey(),
            $booking->getKey()
        );
        $participantName = filled($user->name) ? $user->name : 'Usuario';

        return new JoinVideoSessionData(
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
        if (! in_array($booking->status, [
            BookingStatus::Confirmed,
            BookingStatus::Paid,
            BookingStatus::InProgress,
        ], true)) {
            throw new AuthorizationException(
                'La reserva no está disponible para videollamada.'
            );
        }

        if (! in_array($booking->modality, ['remota', 'hibrida'], true)) {
            throw new AuthorizationException(
                'La modalidad de la reserva no permite videollamada.'
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
