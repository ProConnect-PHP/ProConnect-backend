<?php

namespace App\Models\Video;

use App\Models\User\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'video_session_id',
    'user_id',
    'role',
    'provider_identity',
    'display_name',
    'first_joined_at',
    'last_joined_at',
    'left_at',
    'join_count',
    'metadata',
])]
class VideoSessionParticipant extends Model
{
    use HasFactory;
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected function casts(): array
    {
        return [
            'first_joined_at' => 'datetime',
            'last_joined_at' => 'datetime',
            'left_at' => 'datetime',
            'join_count' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function videoSession(): BelongsTo
    {
        return $this->belongsTo(VideoSession::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
