<?php

namespace App\Models\Notification;

use App\Models\User\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'recipient_id',
    'type',
    'title',
    'message',
    'action_route',
    'metadata',
    'read_at',
    'archived_at',
])]
#[Hidden([
    'updated_at',
])]
#[Table('notifications')]
class Notification extends Model
{
    use HasUuids;

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'read_at' => 'datetime',
            'archived_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_id');
    }

    public function isRead(): bool
    {
        return $this->read_at !== null;
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    public function markAsRead(): void
    {
        if ($this->read_at !== null) {
            return;
        }

        $this->forceFill([
            'read_at' => now(),
        ])->save();
    }

    public function archive(): void
    {
        if ($this->archived_at !== null) {
            return;
        }

        $this->forceFill([
            'archived_at' => now(),
        ])->save();
    }

    public function unarchive(): void
    {
        if ($this->archived_at === null) {
            return;
        }

        $this->forceFill([
            'archived_at' => null,
        ])->save();
    }
}
