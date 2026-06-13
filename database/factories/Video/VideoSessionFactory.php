<?php

namespace Database\Factories\Video;

use App\Enums\Video\VideoProvider;
use App\Enums\Video\VideoSessionStatus;
use App\Models\Booking\Booking;
use App\Models\Video\VideoSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VideoSession>
 */
class VideoSessionFactory extends Factory
{
    protected $model = VideoSession::class;

    public function definition(): array
    {
        $booking = Booking::factory()->paid()->create();
        $roomName = 'booking-'.$booking->id;

        return [
            'booking_id' => $booking->id,
            'client_id' => $booking->client_id,
            'professional_id' => $booking->professional_id,
            'provider' => VideoProvider::Simulator,
            'status' => VideoSessionStatus::Scheduled,
            'room_name' => $roomName,
            'join_url' => rtrim((string) config('proconnect.video.simulator.base_url'), '/').'/'.$roomName,
            'provider_room_id' => null,
            'provider_metadata' => [
                'mode' => 'simulator',
                'created_by' => 'factory',
            ],
            'scheduled_start_at' => $booking->starts_at,
            'scheduled_end_at' => $booking->ends_at,
            'opened_at' => null,
            'started_at' => null,
            'ended_at' => null,
            'cancelled_at' => null,
            'expired_at' => null,
        ];
    }

    public function forBooking(Booking $booking): static
    {
        $roomName = 'booking-'.$booking->id;

        return $this->state(fn () => [
            'booking_id' => $booking->id,
            'client_id' => $booking->client_id,
            'professional_id' => $booking->professional_id,
            'room_name' => $roomName,
            'join_url' => rtrim((string) config('proconnect.video.simulator.base_url'), '/').'/'.$roomName,
            'scheduled_start_at' => $booking->starts_at,
            'scheduled_end_at' => $booking->ends_at,
        ]);
    }

    public function scheduled(): static
    {
        return $this->state(fn () => [
            'status' => VideoSessionStatus::Scheduled,
            'opened_at' => null,
            'started_at' => null,
            'ended_at' => null,
            'cancelled_at' => null,
        ]);
    }

    public function inProgress(): static
    {
        return $this->state(fn () => [
            'status' => VideoSessionStatus::InProgress,
            'opened_at' => now(),
            'started_at' => now(),
            'ended_at' => null,
            'cancelled_at' => null,
        ]);
    }

    public function ended(): static
    {
        return $this->state(fn () => [
            'status' => VideoSessionStatus::Ended,
            'ended_at' => now(),
            'cancelled_at' => null,
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn () => [
            'status' => VideoSessionStatus::Cancelled,
            'cancelled_at' => now(),
        ]);
    }

    public function simulator(): static
    {
        return $this->state(fn () => [
            'provider' => VideoProvider::Simulator,
        ]);
    }
}
