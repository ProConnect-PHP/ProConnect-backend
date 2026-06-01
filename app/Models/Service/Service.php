<?php

namespace App\Models\Service;

use App\Models\Availability\AvailabilityException;
use App\Models\Availability\AvailabilityRule;
use App\Models\Company\Company;
use App\Models\User\ProfessionalProfile;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'professional_id',
    'company_id',
    'name',
    'description',
    'price',
    'duration_minutes',
    'modality',
    'address',
    'link',
    'latitude',
    'longitude',
    'max_bookings_per_client',
    'min_reschedule_minutes',
    'buffer_minutes',
    'starts_at',
    'ends_at',
    'is_active',
])]
class Service extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',

            'duration_minutes' => 'integer',

            'latitude' => 'float',

            'longitude' => 'float',

            'max_bookings_per_client' => 'integer',

            'min_reschedule_minutes' => 'integer',

            'buffer_minutes' => 'integer',

            'starts_at' => 'date',

            'ends_at' => 'date',

            'is_active' => 'boolean',
        ];
    }

    public function professional(): BelongsTo
    {
        return $this->belongsTo(
            ProfessionalProfile::class,
            'professional_id'
        );
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function availabilityRules(): HasMany
    {
        return $this->hasMany(AvailabilityRule::class);
    }

    public function availabilityExceptions(): HasMany
    {
        return $this->hasMany(AvailabilityException::class);
    }
}
