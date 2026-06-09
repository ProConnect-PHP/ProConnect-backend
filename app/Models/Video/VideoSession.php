<?php

namespace App\Models\Video;

use App\Enums\Video\VideoProvider;
use App\Enums\Video\VideoSessionStatus;
use App\Models\Booking\Booking;
use App\Models\User\ProfessionalProfile;
use App\Models\User\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'booking_id',
    'client_id',
    'professional_id',
    'provider',
    'status',
    'room_name',
    'join_url',
    'provider_room_id',
    'provider_metadata',
    'scheduled_start_at',
    'scheduled_end_at',
    'opened_at',
    'started_at',
    'ended_at',
    'cancelled_at',
    'expired_at',
])]
class VideoSession extends Model
{
    use HasFactory;
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected function casts(): array
    {
        return [
            'provider' => VideoProvider::class,
            'status' => VideoSessionStatus::class,
            'provider_metadata' => 'array',
            'scheduled_start_at' => 'datetime',
            'scheduled_end_at' => 'datetime',
            'opened_at' => 'datetime',
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'expired_at' => 'datetime',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function professional(): BelongsTo
    {
        return $this->belongsTo(ProfessionalProfile::class, 'professional_id');
    }

    public function participants(): HasMany
    {
        return $this->hasMany(VideoSessionParticipant::class);
    }

    public function isJoinWindowOpen(): bool
    {
        $before = (int) config('proconnect.video.join_before_minutes', 15);
        $after = (int) config('proconnect.video.join_after_minutes', 120);

        if (! $this->scheduled_start_at || ! $this->scheduled_end_at) {
            return false;
        }

        return now()->betweenIncluded(
            $this->scheduled_start_at->copy()->subMinutes($before),
            $this->scheduled_end_at->copy()->addMinutes($after)
        );
    }

    public function hasEnded(): bool
    {
        return $this->status === VideoSessionStatus::Ended || $this->ended_at !== null;
    }

    public function isCancelled(): bool
    {
        return $this->status === VideoSessionStatus::Cancelled || $this->cancelled_at !== null;
    }
}
