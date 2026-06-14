<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Facades\JWTAuth;

class EnsureJwtWasIssuedAfterPasswordChange
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user || !$user->password_changed_at) {
            return $next($request);
        }

        try {
            $payload = JWTAuth::parseToken()->getPayload();
            $issuedAt = (int) $payload->get('iat');
        } catch (JWTException) {
            return response()->json([
                'status' => 'error',
                'message' => 'La sesión no es válida. Iniciá sesión nuevamente.',
            ], 401);
        }

        $passwordChangedAt = $user->password_changed_at->timestamp;

        if ($issuedAt < $passwordChangedAt) {
            return response()->json([
                'status' => 'error',
                'message' => 'Tu sesión expiró porque la contraseña fue actualizada. Iniciá sesión nuevamente.',
            ], 401);
        }

        return $next($request);
    }
}
