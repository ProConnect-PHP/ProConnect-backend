<?php

namespace App\Models\Booking;

use App\Enums\Booking\BookingReminderDeliveryStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'booking_id',
    'reminder_rule_id',
    'scheduled_for',
    'sent_at',
    'status',
    'failure_reason',
])]
class BookingReminderDelivery extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected function casts(): array
    {
        return [
            'scheduled_for' => 'datetime',
            'sent_at' => 'datetime',
            'status' => BookingReminderDeliveryStatus::class,
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function reminderRule(): BelongsTo
    {
        return $this->belongsTo(ProfessionalBookingReminderRule::class, 'reminder_rule_id');
    }
}
