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
    'exception_date',
    'is_unavailable',
    'alt_start',
    'alt_end',
    'reason',
])]
class AvailabilityException extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected function casts(): array
    {
        return [
            'exception_date' => 'date',
            'is_unavailable' => 'boolean',
        ];
    }

    public function service() : BelongsTo
    {
        return $this->belongsTo(Service::class);
    }
}
