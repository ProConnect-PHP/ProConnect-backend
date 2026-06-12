<?php

namespace App\Actions\Auth;

use App\Models\User\RefreshToken;
use App\Support\ActivityLog\ActivityLogActorMode;
use App\Support\ActivityLog\ActivityLogEvent;
use App\Support\ActivityLog\ActivityLogger;
use Tymon\JWTAuth\JWTGuard;

class LogoutAction
{
    public function __construct(
        private readonly ActivityLogger $activityLogger,
    ) {}

    public function __invoke(): void
    {
        /** @var JWTGuard $guard */
        $guard = auth('user_jwt');
        $user = $guard->user();

        RefreshToken::where('user_id', $user->id)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);

        $guard->logout();

        $this->activityLogger->record(
            event: ActivityLogEvent::AuthLogout,
            entityType: 'user',
            entityId: $user->id,
            entityOwnerId: $user->id,
            actor: $user,
            actingAs: ActivityLogActorMode::fromRole($user->role),
        );
    }
}
