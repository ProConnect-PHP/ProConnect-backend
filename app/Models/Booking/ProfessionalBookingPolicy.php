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
    'allow_client_cancellation',
    'cancellation_cutoff_minutes',
    'allow_client_rescheduling',
    'rescheduling_cutoff_minutes',
    'late_tolerance_minutes',
    'reminders_enabled',
    'cancellation_policy_text',
    'rescheduling_policy_text',
])]
class ProfessionalBookingPolicy extends Model
{
    use HasUuids;

    public const DEFAULTS = [
        'allow_client_cancellation' => true,
        'cancellation_cutoff_minutes' => 120,
        'allow_client_rescheduling' => true,
        'rescheduling_cutoff_minutes' => 120,
        'late_tolerance_minutes' => 10,
        'reminders_enabled' => true,
        'cancellation_policy_text' => 'Puedes cancelar hasta 2 horas antes del inicio de la sesion.',
        'rescheduling_policy_text' => 'Puedes reprogramar hasta 2 horas antes del inicio de la sesion.',
    ];

    public $incrementing = false;

    protected $keyType = 'string';

    protected function casts(): array
    {
        return [
            'allow_client_cancellation' => 'boolean',
            'cancellation_cutoff_minutes' => 'integer',
            'allow_client_rescheduling' => 'boolean',
            'rescheduling_cutoff_minutes' => 'integer',
            'late_tolerance_minutes' => 'integer',
            'reminders_enabled' => 'boolean',
        ];
    }

    public function professional(): BelongsTo
    {
        return $this->belongsTo(ProfessionalProfile::class, 'professional_id');
    }

    public function reminderRules(): HasMany
    {
        return $this->hasMany(
            ProfessionalBookingReminderRule::class,
            'professional_id',
            'professional_id'
        );
    }
}
