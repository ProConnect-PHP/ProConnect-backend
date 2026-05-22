<?php
// App/Actions/Auth/RefreshTokenAction.php

namespace App\Actions\Auth;

use App\Models\User\RefreshToken;

class RefreshTokenAction
{
    public function __invoke(string $refreshToken): array
    {
        $token = RefreshToken::where('token', $refreshToken)
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->first();

        if (!$token) {
            return ['access_token' => null, 'refresh_token' => null];
        }

        $token->update(['revoked_at' => now()]);

        $user = $token->user;
        /** @var \Tymon\JWTAuth\JWTGuard $guard */
        $guard = auth('user_jwt');
        $newAccessToken = $guard->login($user);

        $newRefreshToken = RefreshToken::create([
            'user_id'    => $user->id,
            'token'      => bin2hex(random_bytes(64)),
            'expires_at' => now()->addDays(7),
        ]);

        return [
            'access_token'  => $newAccessToken,
            'refresh_token' => $newRefreshToken->token,
            'expires_in'    => $guard->factory()->getTTL() * 60,
        ];
    }
}
