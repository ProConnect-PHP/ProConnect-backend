<?php

namespace App\Http\Controllers\Password;

use App\Http\Controllers\Controller;
use App\Models\User\User;
use Carbon\Carbon;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password as PasswordFacade;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class ResetPasswordController extends Controller
{
    private const PASSWORD_CHANGE_COOLDOWN_SECONDS = 60;

    /**
     * Enviar enlace de recuperación.
     *
     * Ruta sugerida:
     * POST /api/v1/auth/account/password-reset
     *
     * Sirve para:
     * - Flujo público: usuario no autenticado envía email.
     * - Flujo interno: frontend puede enviar el email del usuario autenticado.
     */
    public function sendResetLink(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['nullable', 'email', 'max:255'],
        ]);

        $email = $request->user()?->email ?? $validated['email'] ?? null;

        if (!$email) {
            return response()->json([
                'status' => 'error',
                'message' => 'El correo electrónico es obligatorio.',
            ], 422);
        }

        try {
            $status = PasswordFacade::broker()->sendResetLink([
                'email' => $email,
            ]);
        } catch (TransportExceptionInterface $exception) {
            Log::error('Password reset email transport failed.', [
                'email' => $email,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'No pudimos enviar el correo en este momento. Intentá nuevamente más tarde.',
            ], 503);
        }

        if ($status === PasswordFacade::RESET_LINK_SENT) {
            return response()->json([
                'status' => 'success',
                'message' => 'Si la cuenta existe, te enviaremos un enlace seguro para restablecer la contraseña.',
            ]);
        }

        if ($status === PasswordFacade::RESET_THROTTLED) {
            return response()->json([
                'status' => 'error',
                'message' => 'Por seguridad, esperá unos minutos antes de solicitar otro enlace.',
            ], 429);
        }

        if ($status === PasswordFacade::INVALID_USER) {
            /*
             * Importante:
             * No devolvemos 404 porque eso permite enumerar usuarios registrados.
             * Para un flujo público de password reset, la respuesta debe ser genérica.
             */
            return response()->json([
                'status' => 'success',
                'message' => 'Si la cuenta existe, te enviaremos un enlace seguro para restablecer la contraseña.',
            ]);
        }

        Log::warning('Unexpected password reset link status.', [
            'email' => $email,
            'status' => $status,
        ]);

        return response()->json([
            'status' => 'error',
            'message' => 'No se pudo procesar la solicitud de restablecimiento.',
        ], 500);
    }

    /**
     * Procesar cambio de contraseña.
     *
     * Ruta sugerida:
     * POST /api/v1/auth/password-update
     */
    public function updatePassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email', 'max:255'],
            'password' => [
                'required',
                'confirmed',
                PasswordRule::min(8),
                'max:128',
            ],
        ]);

        $user = User::query()
            ->where('email', $validated['email'])
            ->first();

        if ($user) {
            $secondsRemaining = $this->secondsUntilPasswordChangeAllowed($user);

            if ($secondsRemaining > 0) {
                return response()->json([
                    'status' => 'error',
                    'message' => "Por seguridad, debés esperar {$secondsRemaining} segundos antes de cambiar nuevamente la contraseña.",
                ], 429);
            }
        }

        $status = PasswordFacade::broker()->reset(
            [
                'email' => $validated['email'],
                'password' => $validated['password'],
                'password_confirmation' => $request->input('password_confirmation'),
                'token' => $validated['token'],
            ],
            function (User $user, string $password): void {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'password_changed_at' => now(),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            },
        );

        if ($status === PasswordFacade::PASSWORD_RESET) {
            return response()->json([
                'status' => 'success',
                'message' => 'Tu contraseña fue actualizada correctamente. Ya podés iniciar sesión.',
            ]);
        }

        if ($status === PasswordFacade::INVALID_TOKEN) {
            return response()->json([
                'status' => 'error',
                'message' => 'El enlace de restablecimiento no es válido o ya expiró.',
            ], 422);
        }

        if ($status === PasswordFacade::INVALID_USER) {
            return response()->json([
                'status' => 'error',
                'message' => 'El enlace de restablecimiento no es válido o ya expiró.',
            ], 422);
        }

        Log::warning('Unexpected password reset status.', [
            'email' => $validated['email'],
            'status' => $status,
        ]);

        return response()->json([
            'status' => 'error',
            'message' => 'No pudimos actualizar la contraseña en este momento.',
        ], 500);
    }

    private function secondsUntilPasswordChangeAllowed(User $user): int
    {
        if (!$user->password_changed_at) {
            return 0;
        }

        $changedAt = Carbon::parse($user->password_changed_at);
        $availableAt = $changedAt->copy()->addSeconds(self::PASSWORD_CHANGE_COOLDOWN_SECONDS);

        if ($availableAt->isPast()) {
            return 0;
        }

        return max(1, now()->diffInSeconds($availableAt, false));
    }
}
