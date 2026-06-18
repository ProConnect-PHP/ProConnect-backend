<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Logs\ActivityLog;
use App\Support\ActivityLog\ActivityLogActorMode;
use App\Support\ActivityLog\ActivityLogEvent;
use App\Support\ActivityLog\ActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class ActivityLogController extends Controller
{
    public function index(
        Request $request,
        ActivityLogger $activityLogger,
    ): JsonResponse {
        $filters = $request->validate([
            'event' => ['nullable', 'string', 'max:120'],
            'actor_id' => ['nullable', 'uuid'],
            'actor_role' => ['nullable', 'in:guest,client,professional,admin,system'],
            'acting_as' => ['nullable', 'in:guest,client,professional,admin,system'],
            'entity_type' => ['nullable', 'string', 'max:120'],
            'entity_id' => ['nullable', 'string', 'max:120'],
            'severity' => ['nullable', 'in:debug,info,notice,warning,error,critical,alert,emergency'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:200'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $query = ActivityLog::query()->orderByDesc('created_at');

        foreach (['event', 'actor_id', 'actor_role', 'acting_as', 'entity_type', 'entity_id', 'severity'] as $filter) {
            if (filled($filters[$filter] ?? null)) {
                $query->where($filter, $filters[$filter]);
            }
        }

        if (filled($filters['date_from'] ?? null)) {
            $query->where('created_at', '>=', Carbon::parse($filters['date_from'])->startOfDay());
        }

        if (filled($filters['date_to'] ?? null)) {
            $query->where('created_at', '<=', Carbon::parse($filters['date_to'])->endOfDay());
        }

        $perPage = (int) ($filters['per_page'] ?? $filters['limit'] ?? 50);
        $logs = $query->paginate($perPage);

        $activityLogger->record(
            event: ActivityLogEvent::AdminActivityLogViewed,
            entityType: 'activity_log',
            metadata: [
                'filters' => array_diff_key($filters, ['limit' => true, 'per_page' => true, 'page' => true]),
                'result_count' => $logs->count(),
            ],
            actor: $request->user('user_jwt'),
            actingAs: ActivityLogActorMode::Admin,
        );

        return response()->json([
            'data' => $logs->items(),
            'meta' => [
                'current_page' => $logs->currentPage(),
                'per_page' => $logs->perPage(),
                'total' => $logs->total(),
                'last_page' => $logs->lastPage(),
            ],
        ]);
    }
}
