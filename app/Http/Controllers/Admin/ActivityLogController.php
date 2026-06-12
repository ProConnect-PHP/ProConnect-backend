<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Logs\ActivityLog;
use App\Support\ActivityLog\ActivityLogActorMode;
use App\Support\ActivityLog\ActivityLogEvent;
use App\Support\ActivityLog\ActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ActivityLogController extends Controller
{
    public function index(
        Request $request,
        ActivityLogger $activityLogger,
    ): JsonResponse {
        $filters = $request->validate([
            'event' => ['nullable', 'string', 'max:120'],
            'actor_id' => ['nullable', 'uuid'],
            'acting_as' => ['nullable', 'in:guest,client,professional,admin,system'],
            'entity_type' => ['nullable', 'string', 'max:120'],
            'entity_id' => ['nullable', 'string', 'max:120'],
            'severity' => ['nullable', 'in:debug,info,notice,warning,error,critical,alert,emergency'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        $query = ActivityLog::query()->orderByDesc('created_at');

        foreach (['event', 'actor_id', 'acting_as', 'entity_type', 'entity_id', 'severity'] as $filter) {
            if (filled($filters[$filter] ?? null)) {
                $query->where($filter, $filters[$filter]);
            }
        }

        $logs = $query->limit((int) ($filters['limit'] ?? 50))->get();

        $activityLogger->record(
            event: ActivityLogEvent::AdminActivityLogViewed,
            entityType: 'activity_log',
            metadata: [
                'filters' => array_diff_key($filters, ['limit' => true]),
                'result_count' => $logs->count(),
            ],
            actor: $request->user('user_jwt'),
            actingAs: ActivityLogActorMode::Admin,
        );

        return response()->json([
            'data' => $logs,
        ]);
    }
}
