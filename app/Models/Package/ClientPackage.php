<?php

namespace App\Models\Package;

use App\Enums\Package\ClientPackageStatus;
use App\Models\Service\Service;
use App\Models\User\ProfessionalProfile;
use App\Models\User\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'package_product_id',
    'client_id',
    'professional_id',
    'service_id',
    'status',
    'total_sessions',
    'used_sessions',
    'price_snapshot',
    'currency',
    'purchased_at',
    'expires_at',
    'cancelled_at',
    'depleted_at',
    'metadata',
])]
class ClientPackage extends Model
{
    use HasFactory;
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected function casts(): array
    {
        return [
            'status' => ClientPackageStatus::class,
            'total_sessions' => 'integer',
            'used_sessions' => 'integer',
            'price_snapshot' => 'integer',
            'metadata' => 'array',
            'purchased_at' => 'datetime',
            'expires_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'depleted_at' => 'datetime',
        ];
    }

    public function packageProduct(): BelongsTo
    {
        return $this->belongsTo(PackageProduct::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function professional(): BelongsTo
    {
        return $this->belongsTo(ProfessionalProfile::class, 'professional_id');
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(PackageSession::class);
    }

    public function remainingSessions(): int
    {
        return max(0, $this->total_sessions - $this->used_sessions);
    }

    public function isActive(): bool
    {
        return $this->status === ClientPackageStatus::Active
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }

    public function hasRemainingSessions(): bool
    {
        return $this->remainingSessions() > 0;
    }
}
