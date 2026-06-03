<?php

namespace App\Models\Review;

use App\Models\Booking\Booking;
use App\Models\Service\Service;
use App\Models\User\ProfessionalProfile;
use App\Models\User\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'booking_id',
    'service_id',
    'professional_id',
    'client_id',
    'rating',
    'comment',
    'edited_at',
    'comment_deleted_at',
])]
class Review extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    public $incrementing = false;

    protected $keyType = 'string';

    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'edited_at' => 'datetime',
            'comment_deleted_at' => 'datetime',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
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

    public function reply(): HasOne
    {
        return $this->hasOne(ReviewReply::class);
    }

    public function canBeEdited(): bool
    {
        $days = (int) config('proconnect.reviews.edit_window_days', 7);

        return $this->created_at?->greaterThanOrEqualTo(now()->subDays($days)) ?? false;
    }

    public function canCommentBeDeleted(): bool
    {
        return $this->canBeEdited();
    }
}
