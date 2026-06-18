<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Exceptions\ApiExceptionRenderer;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\AdminUserResource;
use App\Models\User\User;
use App\Support\ActivityLog\ActivityLogActorMode;
use App\Support\ActivityLog\ActivityLogEvent;
use App\Support\ActivityLog\ActivityLogger;
use BackedEnum;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class AdminUserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'role' => ['nullable', Rule::enum(UserRole::class)],
            'search' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', 'in:active,disabled'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $users = User::query()
            ->when($filters['role'] ?? null, fn ($query, string $role) => $query->where('role', $role))
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when($filters['search'] ?? null, function ($query, string $search): void {
                $query->where(function ($query) use ($search): void {
                    $query
                        ->where('name', 'ilike', "%{$search}%")
                        ->orWhere('email', 'ilike', "%{$search}%");
                });
            })
            ->orderByDesc('created_at')
            ->paginate((int) ($filters['per_page'] ?? 15));

        return response()->json([
            'data' => AdminUserResource::collection($users->items()),
            'meta' => [
                'current_page' => $users->currentPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
                'last_page' => $users->lastPage(),
            ],
        ]);
    }

    public function show(User $user): JsonResponse
    {
        $user->load('professionalProfile');

        return response()->json([
            'data' => array_merge(
                (new AdminUserResource($user))->resolve(),
                [
                    'professional_profile' => $user->professionalProfile
                        ? [
                            'id' => $user->professionalProfile->id,
                            'services_count' => $user->professionalProfile->services()->count(),
                        ]
                        : null,
                    'bookings_count' => $user->bookings()->count(),
                    'payments_count' => $user->payments()->count(),
                ]
            ),
        ]);
    }

    public function updateStatus(
        Request $request,
        User $user,
        ActivityLogger $activityLogger,
    ): JsonResponse {
        $validated = $request->validate([
            'status' => ['required', 'string', 'in:active,disabled'],
        ]);

        $newStatus = $validated['status'];
        $oldStatus = $user->status ?? 'active';

        if ($newStatus === 'disabled' && $user->hasRole(UserRole::Admin) && $oldStatus !== 'disabled') {
            $activeAdmins = User::query()
                ->where('role', UserRole::Admin->value)
                ->where('status', 'active')
                ->count();

            if ($activeAdmins <= 1) {
                return ApiExceptionRenderer::render(
                    error: 'LastActiveAdmin',
                    message: 'Cannot disable the last active administrator.',
                    status: Response::HTTP_CONFLICT
                );
            }
        }

        $user->update(['status' => $newStatus]);

        /** @var User|null $admin */
        $admin = $request->user('user_jwt');

        $activityLogger->record(
            event: ActivityLogEvent::AdminUserStatusUpdated,
            entityType: 'user',
            entityId: $user->id,
            entityOwnerId: $user->id,
            metadata: [
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'target_role' => $this->enumValue($user->role),
            ],
            statusCode: Response::HTTP_OK,
            actor: $admin,
            actingAs: ActivityLogActorMode::Admin,
        );

        return response()->json([
            'data' => new AdminUserResource($user->refresh()),
        ]);
    }

    private function enumValue(mixed $value): mixed
    {
        return $value instanceof BackedEnum ? $value->value : $value;
    }
}
