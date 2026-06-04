<?php

namespace App\Models\Notification;

use App\Models\Booking\Booking;
use App\Models\Package\ClientPackage;
use App\Models\Package\PackageSession;
use App\Models\User\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'booking_id',
    'client_package_id',
    'package_session_id',
    'channel',
    'type',
    'recipient',
    'status',
    'payload',
    'error',
    'sent_at',
])]
class NotificationLog extends Model
{
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'sent_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function clientPackage(): BelongsTo
    {
        return $this->belongsTo(ClientPackage::class);
    }

    public function packageSession(): BelongsTo
    {
        return $this->belongsTo(PackageSession::class);
    }
}
