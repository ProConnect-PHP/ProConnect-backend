<?php

namespace App\Services\Auth;

use App\Http\Resources\User\UserResource;
use App\Models\User\RefreshToken;
use App\Models\User\User;
use Tymon\JWTAuth\JWTGuard;

final class AuthTokenIssuer
{
    /**
     * @return array{
     *     access_token: string,
     *     refresh_token: string,
     *     token_type: string,
     *     expires_in: int,
     *     user: UserResource
     * }
     */
    public function issueForUser(User $user): array
    {
        /** @var JWTGuard $guard */
        $guard = auth('user_jwt');
        $accessToken = $guard->login($user);

        $refreshToken = RefreshToken::query()->create([
            'user_id' => $user->id,
            'token' => bin2hex(random_bytes(64)),
            'expires_at' => now()->addDays(7),
        ]);

        return [
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken->token,
            'token_type' => 'bearer',
            'expires_in' => $guard->factory()->getTTL() * 60,
            'user' => new UserResource($user),
        ];
    }
}
