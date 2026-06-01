<?php

namespace App\Models\Availability;

use App\Models\Service\Service;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'service_id',
    'day_of_week',
    'start_time',
    'end_time',
    'is_active',
])]
class AvailabilityRule extends Model
{
    use HasUuids, HasFactory, SoftDeletes;
    protected function casts(): array
    {
        return [
            'day_of_week' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function service() : BelongsTo
    {
        return $this->belongsTo(Service::class);
    }
}
