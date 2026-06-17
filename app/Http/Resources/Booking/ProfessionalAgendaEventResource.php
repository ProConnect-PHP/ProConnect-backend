<?php

namespace App\Http\Resources\Booking;

use App\Enums\Booking\BookingStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProfessionalAgendaEventResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $status = $this->status instanceof BookingStatus
            ? $this->status->value
            : $this->status;

        $paymentStatus = $this->relationLoaded('payment')
            ? ($this->payment?->status?->value ?? $this->payment?->status)
            : null;

        $hasVideoSession = $this->relationLoaded('videoSession')
            && $this->videoSession !== null;

        return [
            'id' => $this->id,
            'type' => 'booking',
            'title' => $this->service?->name ?? 'Reserva',
            'starts_at' => $this->starts_at?->toDateTimeString(),
            'ends_at' => $this->ends_at?->toDateTimeString(),
            'status' => $status,
            'modality' => $this->modality,

            'service' => $this->whenLoaded('service', fn () => [
                'id' => $this->service?->id,
                'name' => $this->service?->name,
                'duration_minutes' => $this->service?->duration_minutes,
                'modality' => $this->service?->modality,
                'address' => $this->service?->address,
            ]),

            'client' => $this->whenLoaded('client', fn () => [
                'id' => $this->client?->id,
                'name' => $this->client?->name,
                'avatar_url' => $this->client?->avatar_url,
            ]),

            'payment_status' => $paymentStatus,
            'payment_source' => $this->client_package_id ? 'package' : ($this->payment ? 'payment' : null),

            'client_package' => $this->whenLoaded('clientPackage', fn () => [
                'id' => $this->clientPackage?->id,
                'status' => $this->clientPackage?->status?->value ?? $this->clientPackage?->status,
                'total_sessions' => $this->clientPackage?->total_sessions,
                'used_sessions' => $this->clientPackage?->used_sessions,
                'remaining_sessions' => $this->clientPackage?->remainingSessions(),
                'expires_at' => $this->clientPackage?->expires_at?->toDateTimeString(),
            ]),

            'package_session' => $this->whenLoaded('packageSession', fn () => [
                'id' => $this->packageSession?->id,
                'status' => $this->packageSession?->status?->value ?? $this->packageSession?->status,
                'consumed_at' => $this->packageSession?->consumed_at?->toDateTimeString(),
                'released_at' => $this->packageSession?->released_at?->toDateTimeString(),
            ]),

            'video_session' => $this->when($hasVideoSession, fn () => [
                'id' => $this->videoSession?->id,
                'status' => $this->videoSession?->status?->value ?? $this->videoSession?->status,
                'scheduled_start_at' => $this->videoSession?->scheduled_start_at?->toDateTimeString(),
                'scheduled_end_at' => $this->videoSession?->scheduled_end_at?->toDateTimeString(),
                'can_join_now' => $this->canJoinVideoSession()
                    && $this->videoSession?->isJoinWindowOpen()
                    && ! $this->videoSession?->hasEnded()
                    && ! $this->videoSession?->isCancelled(),
            ]),

            'flags' => [
                'is_cancelled' => $status === BookingStatus::Cancelled->value,
                'is_pending' => $status === BookingStatus::Pending->value,
                'is_confirmed' => $status === BookingStatus::Confirmed->value,
                'is_paid' => $status === BookingStatus::Paid->value,
                'is_completed' => $status === BookingStatus::Completed->value,
                'is_no_show' => $status === BookingStatus::NoShow->value,
                'has_video_session' => $hasVideoSession,
                'uses_package' => $this->client_package_id !== null,
            ],

            'created_at' => $this->created_at?->toDateTimeString(),
        ];
    }
}
