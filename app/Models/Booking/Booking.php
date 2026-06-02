<?php

namespace App\Models\Booking;

use App\Enums\Booking\BookingStatus;
use App\Models\Service\Service;
use App\Models\User\ProfessionalProfile;
use App\Models\User\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'service_id',
    'professional_id',
    'client_id',
    'starts_at',
    'ends_at',
    'status',
    'modality',
    'price_snapshot',
    'duration_minutes_snapshot',
    'confirmed_at',
    'cancelled_at',
    'paid_at',
    'completed_at',
    'no_show_at',
    'cancellation_reason',
    'reschedule_reason',
])]
class Booking extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    public $incrementing = false;

    protected $keyType = 'string';

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'status' => BookingStatus::class,
            'price_snapshot' => 'decimal:2',
            'duration_minutes_snapshot' => 'integer',
            'confirmed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'paid_at' => 'datetime',
            'completed_at' => 'datetime',
            'no_show_at' => 'datetime',
        ];
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function professional(): BelongsTo
    {
        return $this->belongsTo(ProfessionalProfile::class, 'professional_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function isCancellable(): bool
    {
        return in_array($this->status, [
            BookingStatus::Pending,
            BookingStatus::Confirmed,
            BookingStatus::Paid,
        ], true);
    }

    public function isReschedulable(): bool
    {
        return in_array($this->status, [
            BookingStatus::Pending,
            BookingStatus::Confirmed,
        ], true);
    }
}
