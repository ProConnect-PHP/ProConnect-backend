<?php

namespace App\Actions\Auth;

use App\Models\User\RefreshToken;
use Tymon\JWTAuth\JWTGuard;

class LogoutAction
{
    public function __invoke(): void
    {
        /** @var JWTGuard $guard */
        $guard = auth('user_jwt');

        RefreshToken::where('user_id', $guard->user()->id)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);

        $guard->logout();
    }
}
