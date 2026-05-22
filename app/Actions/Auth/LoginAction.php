<?php

// App/Actions/Auth/LoginAction.php

namespace App\Actions\Auth;

use App\Http\Requests\Auth\LoginRequest;
use App\Models\User\RefreshToken;
use Tymon\JWTAuth\JWTGuard;

class LoginAction
{
    public function __invoke(LoginRequest $request): array
    {
        $credentials = $request->validated();

        /** @var JWTGuard $guard */
        $guard = auth('user_jwt');
        if (! $token = $guard->attempt($credentials)) {
            return ['token' => null, 'refresh_token' => null, 'user' => null];
        }

        $user = $guard->user();

        $refreshToken = RefreshToken::create([
            'user_id' => $user->id,
            'token' => bin2hex(random_bytes(64)),
            'expires_at' => now()->addDays(7),
        ]);

        return [
            'access_token' => $token,
            'refresh_token' => $refreshToken->token,
            'expires_in' => $guard->factory()->getTTL() * 60,
            'user' => $user,
        ];
    }
}
