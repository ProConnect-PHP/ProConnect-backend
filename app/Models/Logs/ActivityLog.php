<?php

namespace App\Models\Logs;

use MongoDB\Laravel\Eloquent\Model;

class ActivityLog extends Model
{
    protected $connection = 'mongodb';

    protected $table = 'activity_logs';

    public $timestamps = false;

    protected $fillable = [
        'event',
        'severity',
        'actor_id',
        'actor_email',
        'actor_role',
        'actor_type',
        'acting_as',
        'entity_type',
        'entity_id',
        'entity_owner_id',
        'ip',
        'user_agent',
        'request_id',
        'method',
        'path',
        'status_code',
        'metadata',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'status_code' => 'integer',
            'created_at' => 'datetime',
        ];
    }
}
