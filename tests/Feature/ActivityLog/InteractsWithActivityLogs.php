<?php

namespace Tests\Feature\ActivityLog;

use App\Models\Logs\ActivityLog;
use Illuminate\Support\Facades\DB;

trait InteractsWithActivityLogs
{
    protected function clearActivityLogs(): void
    {
        DB::connection('mongodb')
            ->getCollection('activity_logs')
            ->deleteMany([]);
    }

    protected function activityLog(string $event): ?ActivityLog
    {
        return ActivityLog::query()
            ->where('event', $event)
            ->orderByDesc('created_at')
            ->first();
    }
}
