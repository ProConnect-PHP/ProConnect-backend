<?php

namespace App\Http\Resources\Availability;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AvailabilityExceptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'service_id' => $this->service_id,
            'exception_date' => $this->exception_date?->toDateString(),
            'is_unavailable' => $this->is_unavailable,
            'alt_start' => $this->alt_start,
            'alt_end' => $this->alt_end,
            'reason' => $this->reason,
            'created_at' => $this->created_at,
        ];
    }
}
