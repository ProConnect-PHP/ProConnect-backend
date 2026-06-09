<?php

namespace App\Actions\Video;

use App\Enums\Booking\BookingStatus;
use App\Enums\Video\VideoProvider;
use App\Enums\Video\VideoSessionStatus;
use App\Exceptions\ApiException;
use App\Events\Video\VideoSessionCreated;
use App\Models\Booking\Booking;
use App\Models\Video\VideoSession;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class EnsureVideoSessionForBookingAction
{
    public function __invoke(Booking $booking): VideoSession
    {
        return DB::transaction(function () use ($booking) {
            $booking = Booking::query()
                ->with(['service', 'videoSession'])
                ->whereKey($booking->id)
                ->lockForUpdate()
                ->firstOrFail();

            $modality = $booking->modality ?: $booking->service?->modality;

            if (! in_array($modality, ['remota', 'hibrida'], true)) {
                throw new ApiException(
                    error: 'VideoSessionNotAllowedForModality',
                    message: 'Esta reserva no requiere sesion virtual.',
                    status: Response::HTTP_CONFLICT
                );
            }

            if ($booking->videoSession) {
                return $booking->videoSession->load(['booking', 'participants']);
            }

            if (in_array($booking->status, [
                BookingStatus::Cancelled,
                BookingStatus::NoShow,
                BookingStatus::Completed,
            ], true)) {
                throw new ApiException(
                    error: 'BookingNotEligibleForVideoSession',
                    message: 'Esta reserva no puede crear una sesion virtual.',
                    status: Response::HTTP_CONFLICT
                );
            }

            $provider = VideoProvider::tryFrom((string) config('proconnect.video.provider', 'simulator'))
                ?? VideoProvider::Simulator;
            $roomName = 'booking-'.$booking->id;
            $baseUrl = rtrim((string) config('proconnect.video.simulator.base_url'), '/');

            $videoSession = VideoSession::create([
                'booking_id' => $booking->id,
                'client_id' => $booking->client_id,
                'professional_id' => $booking->professional_id,
                'provider' => $provider,
                'status' => VideoSessionStatus::Scheduled,
                'room_name' => $roomName,
                'join_url' => "{$baseUrl}/{$roomName}",
                'scheduled_start_at' => $booking->starts_at,
                'scheduled_end_at' => $booking->ends_at,
                'provider_metadata' => [
                    'mode' => 'simulator',
                    'created_by' => 'system',
                ],
            ]);

            DB::afterCommit(function () use ($videoSession): void {
                event(new VideoSessionCreated($videoSession->id));
            });

            return $videoSession->load(['booking', 'participants']);
        });
    }
}
