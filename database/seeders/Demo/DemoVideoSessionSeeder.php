<?php

namespace Database\Seeders\Demo;

use App\Actions\Video\EnsureVideoSessionForBookingAction;
use App\Enums\Booking\BookingStatus;
use App\Enums\Video\VideoSessionStatus;
use App\Models\Booking\Booking;
use App\Models\Video\VideoSession;
use App\Models\Video\VideoSessionParticipant;
use Illuminate\Database\Seeder;

class DemoVideoSessionSeeder extends Seeder
{
    public function run(): void
    {
        $created = 0;

        foreach ([
            BookingStatus::Confirmed,
            BookingStatus::Paid,
            BookingStatus::InProgress,
            BookingStatus::Completed,
            BookingStatus::Cancelled,
        ] as $status) {
            $isHistorical = in_array($status, [
                BookingStatus::Completed,
                BookingStatus::Cancelled,
            ], true);

            Booking::query()
                ->whereIn('modality', ['remota', 'hibrida'])
                ->where('status', $status->value)
                ->when(
                    ! $isHistorical,
                    fn ($query) => $query->paymentEntitled()
                )
                ->orderBy('starts_at')
                ->take($status === BookingStatus::Confirmed ? 3 : 2)
                ->get()
                ->each(function (Booking $booking) use (&$created): void {
                    if ($booking->status === BookingStatus::Completed || $booking->status === BookingStatus::Cancelled) {
                        $videoSession = $this->upsertHistoricalVideoSession($booking);
                    } else {
                        $videoSession = app(EnsureVideoSessionForBookingAction::class)($booking);
                    }

                    $this->syncStatus($videoSession, $booking);
                    $this->seedParticipants($videoSession);
                    $created++;
                });
        }

        $this->command?->info("Demo video sessions created/updated ({$created} sessions)");
    }

    private function upsertHistoricalVideoSession(Booking $booking): VideoSession
    {
        $roomName = 'booking-'.$booking->id;

        return VideoSession::query()->updateOrCreate(
            ['booking_id' => $booking->id],
            [
                'client_id' => $booking->client_id,
                'professional_id' => $booking->professional_id,
                'provider' => config('proconnect.video.provider', 'simulator'),
                'status' => VideoSessionStatus::Scheduled,
                'room_name' => $roomName,
                'join_url' => rtrim((string) config('proconnect.video.simulator.base_url'), '/').'/'.$roomName,
                'scheduled_start_at' => $booking->starts_at,
                'scheduled_end_at' => $booking->ends_at,
                'provider_metadata' => [
                    'mode' => 'simulator',
                    'created_by' => 'demo_seeder',
                ],
            ]
        );
    }

    private function syncStatus(VideoSession $videoSession, Booking $booking): void
    {
        $status = match ($booking->status) {
            BookingStatus::InProgress => VideoSessionStatus::InProgress,
            BookingStatus::Completed => VideoSessionStatus::Ended,
            BookingStatus::Cancelled => VideoSessionStatus::Cancelled,
            default => VideoSessionStatus::Scheduled,
        };

        $videoSession->update([
            'status' => $status,
            'opened_at' => $status === VideoSessionStatus::InProgress ? now()->subMinutes(10) : null,
            'started_at' => $status === VideoSessionStatus::InProgress ? now()->subMinutes(10) : null,
            'ended_at' => $status === VideoSessionStatus::Ended ? ($booking->completed_at ?? $booking->ends_at) : null,
            'cancelled_at' => $status === VideoSessionStatus::Cancelled ? $booking->cancelled_at : null,
        ]);
    }

    private function seedParticipants(VideoSession $videoSession): void
    {
        if (! in_array($videoSession->status, [VideoSessionStatus::InProgress, VideoSessionStatus::Ended], true)) {
            return;
        }

        $videoSession->load(['client', 'professional.user']);
        $joinedAt = $videoSession->started_at ?? $videoSession->scheduled_start_at;

        foreach ([
            ['user' => $videoSession->client, 'role' => 'client'],
            ['user' => $videoSession->professional?->user, 'role' => 'professional'],
        ] as $participantData) {
            if (! $participantData['user']) {
                continue;
            }

            VideoSessionParticipant::query()->updateOrCreate(
                [
                    'video_session_id' => $videoSession->id,
                    'user_id' => $participantData['user']->id,
                ],
                [
                    'role' => $participantData['role'],
                    'provider_identity' => $participantData['role'].'-'.$participantData['user']->id,
                    'display_name' => $participantData['user']->name,
                    'first_joined_at' => $joinedAt,
                    'last_joined_at' => $joinedAt,
                    'left_at' => $videoSession->ended_at,
                    'join_count' => 1,
                    'metadata' => [
                        'provider' => $videoSession->provider?->value ?? $videoSession->provider,
                        'seeded' => true,
                    ],
                ]
            );
        }
    }
}
