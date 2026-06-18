<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\SendEmailVerificationAction;
use App\Actions\Auth\VerifyEmailAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\EmailVerification\VerifyEmailRequest;
use App\Http\Resources\User\UserResource;
use App\Models\User\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EmailVerificationController extends Controller
{
    public function send(
        Request $request,
        SendEmailVerificationAction $action,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user('user_jwt');

        $result = $action($user);

        if ($result['already_verified']) {
            return response()->json([
                'message' => 'El correo electronico ya esta verificado.',
                'email_verified' => true,
            ]);
        }

        return response()->json([
            'message' => 'Correo de verificacion enviado.',
            'email_verified' => false,
            'expires_at' => $result['expires_at']?->toJSON(),
        ], Response::HTTP_ACCEPTED);
    }

    public function verify(
        VerifyEmailRequest $request,
        VerifyEmailAction $action,
    ): JsonResponse {
        $user = $action(
            email: (string) $request->validated('email'),
            plainToken: (string) $request->validated('token'),
        );

        if (! $user) {
            return response()->json([
                'status' => 'error',
                'error' => [
                    'type' => 'InvalidEmailVerificationToken',
                    'message' => 'El token de verificacion es invalido o expiro.',
                    'code' => 'INVALID_EMAIL_VERIFICATION_TOKEN',
                ],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return response()->json([
            'message' => 'Correo electronico verificado correctamente.',
            'email_verified' => true,
            'user' => new UserResource($user),
        ]);
    }
}
