<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\LoginAction;
use App\Actions\Auth\LogoutAction;
use App\Actions\Auth\RefreshTokenAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\User\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class AuthController extends Controller
{
    public function login(LoginRequest $request, LoginAction $loginAction): JsonResponse
    {
        $result = $loginAction($request);

        if (!$result['access_token']) {
            return response()->json([
                'message' => 'Credenciales incorrectas',
            ], Response::HTTP_UNAUTHORIZED);
        }

        return response()->json([
            'access_token'  => $result['access_token'],
            'refresh_token' => $result['refresh_token'],
            'token_type'    => 'bearer',
            'expires_in'    => $result['expires_in'],
            'user'          => new UserResource($result['user']),
        ], Response::HTTP_OK);
    }

    public function logout(LogoutAction $logoutAction): JsonResponse
    {
        $logoutAction();

        return response()->json([
            'message' => 'Sesión cerrada correctamente',
        ], Response::HTTP_OK);
    }

    public function refresh(Request $request, RefreshTokenAction $refreshTokenAction): JsonResponse
    {
        $result = $refreshTokenAction($request->input('refresh_token'));

        if (!$result['access_token']) {
            return response()->json([
                'message' => 'Refresh token inválido o expirado',
            ], Response::HTTP_UNAUTHORIZED);
        }

        return response()->json([
            'access_token'  => $result['access_token'],
            'refresh_token' => $result['refresh_token'],
            'token_type'    => 'bearer',
            'expires_in'    => $result['expires_in'],
        ], Response::HTTP_OK);
    }
}
