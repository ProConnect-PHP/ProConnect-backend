<?php

namespace App\Http\Resources\Booking;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProfessionalBookingReminderRuleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'minutes_before_start' => $this->minutes_before_start,
            'send_email' => $this->send_email,
            'send_database_notification' => $this->send_database_notification,
            'send_push' => $this->send_push,
            'send_whatsapp' => $this->send_whatsapp,
            'notify_client' => $this->notify_client,
            'notify_professional' => $this->notify_professional,
            'is_active' => $this->is_active,
        ];
    }
}
