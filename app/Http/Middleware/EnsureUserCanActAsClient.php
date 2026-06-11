<?php

namespace App\Http\Middleware;

use App\Models\User\User;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureUserCanActAsClient
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user('user_jwt');

        if (! $user instanceof User) {
            throw new AuthenticationException;
        }

        if (! $user->canActAsClient()) {
            throw new AuthorizationException;
        }

        return $next($request);
    }
}
