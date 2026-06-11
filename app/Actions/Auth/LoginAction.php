<?php

// App/Actions/Auth/LoginAction.php

namespace App\Actions\Auth;

use App\Http\Requests\Auth\LoginRequest;
use App\Services\Auth\AuthTokenIssuer;
use Tymon\JWTAuth\JWTGuard;

class LoginAction
{
    public function __construct(
        private readonly AuthTokenIssuer $tokenIssuer,
    ) {}

    public function __invoke(LoginRequest $request): ?array
    {
        $credentials = $request->validated();

        /** @var JWTGuard $guard */
        $guard = auth('user_jwt');
        if (! $guard->validate($credentials)) {
            return null;
        }

        return $this->tokenIssuer->issueForUser($guard->getLastAttempted());
    }
}
