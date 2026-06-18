<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use App\Exceptions\ApiException;
use App\Models\User\User;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureUserIsAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user('user_jwt');

        if (! $user instanceof User) {
            throw new AuthenticationException;
        }

        if (! $user->hasRole(UserRole::Admin)) {
            throw new ApiException(
                error: 'Forbidden',
                message: 'This action is only available for administrators.',
                status: Response::HTTP_FORBIDDEN
            );
        }

        return $next($request);
    }
}
