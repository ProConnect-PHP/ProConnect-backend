<?php

namespace App\Events\Booking;

use App\Http\Resources\Booking\BookingResource;
use App\Models\Booking\Booking;
use App\Models\User\User;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BookingCompleted implements ShouldBroadcast
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Booking $booking,
        public readonly User $actor,
        public readonly string|\BackedEnum|null $previousStatus = null,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('bookings.client.'.$this->booking->client_id),
            new PrivateChannel('bookings.professional.'.$this->booking->professional_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'booking.completed';
    }

    public function broadcastWith(): array
    {
        $booking = $this->booking->loadMissing([
            'service',
            'professional.user',
            'client',
            'clientPackage.packageProduct',
            'packageSession.clientPackage',
        ]);

        return [
            'booking' => (new BookingResource($booking))->resolve(),
            'previous_status' => $this->previousStatus instanceof \BackedEnum
                ? $this->previousStatus->value
                : $this->previousStatus,
        ];
    }
}
