<?php

namespace App\Http\Middleware;

use App\Models\User\User;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureEmailIsVerified
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user('user_jwt');

        if (! $user instanceof User) {
            throw new AuthenticationException;
        }

        if ($user->email_verified_at === null) {
            return new JsonResponse([
                'status' => 'error',
                'error' => [
                    'type' => 'EmailNotVerified',
                    'message' => 'Debes verificar tu correo electrónico para realizar esta acción.',
                    'code' => 'EMAIL_NOT_VERIFIED',
                ],
            ], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
