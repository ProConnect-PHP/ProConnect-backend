<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\LoginAction;
use App\Actions\Auth\LogoutAction;
use App\Actions\Auth\RefreshTokenAction;
use App\Actions\Auth\RegisterUserAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RefreshTokenRequest;
use App\Http\Requests\Auth\RegisterUserRequest;
use App\Http\Resources\User\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class AuthController extends Controller
{
    /**
     * Store a newly created resource in storage.
     */
    public function register(RegisterUserRequest $registerUserRequest, RegisterUserAction $registerUserAction): JsonResponse
    {
        $user = $registerUserAction($registerUserRequest);

        return response()
            ->json(
                [
                    'message' => 'User created successfully',
                    'user' => new UserResource($user),
                ],
                Response::HTTP_CREATED
            );
    }

    public function login(LoginRequest $request, LoginAction $loginAction): JsonResponse
    {
        $result = $loginAction($request);

        if ($result === null) {
            return response()->json([
                'message' => 'Credenciales incorrectas',
            ], Response::HTTP_UNAUTHORIZED);
        }

        return response()->json($result, Response::HTTP_OK);
    }

    public function logout(LogoutAction $logoutAction): JsonResponse
    {
        $logoutAction();

        return response()->json([
            'message' => 'Sesión cerrada correctamente',
        ], Response::HTTP_OK);
    }

    public function refresh(RefreshTokenRequest $request, RefreshTokenAction $refreshTokenAction): JsonResponse
    {
        $result = $refreshTokenAction($request->input('refresh_token'));

        if (! $result['access_token']) {
            return response()->json([
                'message' => 'Refresh token inválido o expirado',
            ], Response::HTTP_UNAUTHORIZED);
        }

        return response()->json([
            'access_token' => $result['access_token'],
            'refresh_token' => $result['refresh_token'],
            'token_type' => 'bearer',
            'expires_in' => $result['expires_in'],
        ], Response::HTTP_OK);
    }
}
