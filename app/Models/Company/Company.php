<?php

namespace App\Models\Company;

use App\Models\Service\Service;
use App\Models\User\ProfessionalProfile;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'professional_id',
    'commercial_name',
    'legal_name',
    'tax_id',
    'contact_info',
    'is_private',
])]
#[Hidden([
    'deleted_at',
])]
class Company extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected function casts(): array
    {
        return [
            'contact_info' => 'array',
            'is_private' => 'boolean',
        ];
    }

    public function professional(): BelongsTo
    {
        return $this->belongsTo(
            ProfessionalProfile::class,
            'professional_id'
        );
    }

    public function services() : HasMany
    {
        return $this->hasMany(Service::class);
    }
}
