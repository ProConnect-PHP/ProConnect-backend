<?php

// App/Actions/Auth/LoginAction.php

namespace App\Actions\Auth;

use App\Http\Requests\Auth\LoginRequest;
use App\Services\Auth\AuthTokenIssuer;
use App\Support\ActivityLog\ActivityLogActorMode;
use App\Support\ActivityLog\ActivityLogEvent;
use App\Support\ActivityLog\ActivityLogger;
use Symfony\Component\HttpFoundation\Response;
use Tymon\JWTAuth\JWTGuard;

class LoginAction
{
    public function __construct(
        private readonly AuthTokenIssuer $tokenIssuer,
        private readonly ActivityLogger $activityLogger,
    ) {}

    public function __invoke(LoginRequest $request): ?array
    {
        $credentials = $request->validated();

        /** @var JWTGuard $guard */
        $guard = auth('user_jwt');
        if (! $guard->validate($credentials)) {
            $this->activityLogger->record(
                event: ActivityLogEvent::AuthLoginFailed,
                severity: 'warning',
                statusCode: Response::HTTP_UNAUTHORIZED,
                metadata: [
                    'email_attempted' => $credentials['email'],
                    'login_method' => 'password',
                    'reason' => 'invalid_credentials',
                ],
                actingAs: ActivityLogActorMode::Guest,
            );

            return null;
        }

        $user = $guard->getLastAttempted();
        $tokens = $this->tokenIssuer->issueForUser($user);

        $this->activityLogger->record(
            event: ActivityLogEvent::AuthLoginSuccess,
            entityType: 'user',
            entityId: $user->id,
            entityOwnerId: $user->id,
            metadata: ['login_method' => 'password'],
            statusCode: Response::HTTP_OK,
            actor: $user,
            actingAs: ActivityLogActorMode::fromRole($user->role),
        );

        return $tokens;
    }
}
