<?php

namespace App\Models\Booking;

use App\Models\User\ProfessionalProfile;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'professional_id',
    'minutes_before_start',
    'send_email',
    'send_database_notification',
    'send_push',
    'send_whatsapp',
    'notify_client',
    'notify_professional',
    'is_active',
])]
class ProfessionalBookingReminderRule extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected function casts(): array
    {
        return [
            'minutes_before_start' => 'integer',
            'send_email' => 'boolean',
            'send_database_notification' => 'boolean',
            'send_push' => 'boolean',
            'send_whatsapp' => 'boolean',
            'notify_client' => 'boolean',
            'notify_professional' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function professional(): BelongsTo
    {
        return $this->belongsTo(ProfessionalProfile::class, 'professional_id');
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(BookingReminderDelivery::class, 'reminder_rule_id');
    }
}
