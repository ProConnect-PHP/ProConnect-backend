<?php

// App/Actions/Auth/RefreshTokenAction.php

namespace App\Actions\Auth;

use App\Models\User\RefreshToken;
use App\Support\ActivityLog\ActivityLogActorMode;
use App\Support\ActivityLog\ActivityLogEvent;
use App\Support\ActivityLog\ActivityLogger;
use Symfony\Component\HttpFoundation\Response;
use Tymon\JWTAuth\JWTGuard;

class RefreshTokenAction
{
    public function __construct(
        private readonly ActivityLogger $activityLogger,
    ) {}

    public function __invoke(string $refreshToken): array
    {
        $token = RefreshToken::where('token', $refreshToken)
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->first();

        if (! $token) {
            $this->activityLogger->record(
                event: ActivityLogEvent::AuthRefreshFailed,
                severity: 'warning',
                statusCode: Response::HTTP_UNAUTHORIZED,
                metadata: ['reason' => 'invalid_or_expired_refresh_token'],
                actingAs: ActivityLogActorMode::Guest,
            );

            return ['access_token' => null, 'refresh_token' => null];
        }

        $token->update(['revoked_at' => now()]);

        $user = $token->user;
        /** @var JWTGuard $guard */
        $guard = auth('user_jwt');
        $newAccessToken = $guard->login($user);

        $newRefreshToken = RefreshToken::create([
            'user_id' => $user->id,
            'token' => bin2hex(random_bytes(64)),
            'expires_at' => now()->addDays(7),
        ]);

        return [
            'access_token' => $newAccessToken,
            'refresh_token' => $newRefreshToken->token,
            'expires_in' => $guard->factory()->getTTL() * 60,
        ];
    }
}
