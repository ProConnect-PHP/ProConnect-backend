<?php

namespace App\Http\Resources\Booking;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BookingAvailableActionsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'can_cancel' => (bool) $this->resource['can_cancel'],
            'can_reschedule' => (bool) $this->resource['can_reschedule'],
            'cancel_disabled_reason' => $this->resource['cancel_disabled_reason'],
            'reschedule_disabled_reason' => $this->resource['reschedule_disabled_reason'],
        ];
    }
}
