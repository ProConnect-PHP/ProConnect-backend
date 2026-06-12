<?php

namespace App\Http\Controllers\Password;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password as PasswordFacade;

class ResetPasswordController extends Controller
{
    /**
     * FLUJO INTERNO/EXTERNO - PASO 1: Enviar el enlace de recuperación al correo
     * Ruta: /api/v1/auth/account/password-reset
     */
    public function sendResetLink(Request $request)
    {
        // 1. Si el usuario está logueado (Ajustes de cuenta), usamos su email. Si no, lo tomamos del request.
        $email = $request->user() ? $request->user()->email : $request->input('email');

        if (!$email) {
            return response()->json([
                'status' => 'error',
                'message' => 'El correo electrónico es obligatorio.'
            ], 422);
        }

        // 2. Buscar al usuario
        $user = User::where('email', $email)->first();

        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'No se encontró ningún usuario con este correo electrónico.'
            ], 442);
        }

        if ($user->password_changed_at) {
            $fechaCambio = Carbon::parse($user->password_changed_at);

            // PRUEBAS: 1 MINUTO (Cambiar a subDay() para producción de 24 horas)
            if ($fechaCambio->greaterThanOrEqualTo(Carbon::now()->subMinute())) {
                $segundosRestantes = Carbon::now()->diffInSeconds($fechaCambio->addMinute(), false);
                $segundosRestantes = $segundosRestantes > 0 ? $segundosRestantes : 1;

                return response()->json([
                    'status' => 'error',
                    'message' => "Por razones de seguridad, debes esperar para solicitar otro cambio. Inténtalo de nuevo en {$segundosRestantes} segundos."
                ], 400); // Retorna 400 Bad Request para que Angular muestre el cartel rojo
            }
        }
        // =========================================================================

        // 3. Enviamos el link usando el broker nativo de Laravel (creará el token y mandará el mail a Mailpit)
        $status = PasswordFacade::broker()->sendResetLink(['email' => $user->email]);

        // CASO A: El correo se despachó con éxito
        if ($status === PasswordFacade::RESET_LINK_SENT) {
            return response()->json([
                'status' => 'success',
                'message' => '¡Enlace de restablecimiento enviado! Revisá tu correo electrónico.'
            ], 200);
        }

        // CASO B: El broker interno bloqueó el envío por límite de tiempo (Throttle de Laravel)
        if ($status === PasswordFacade::RESET_THROTTLED) {
            return response()->json([
                'status' => 'error',
                'message' => 'Por razones de seguridad, debes esperar un momento antes de solicitar otro enlace.'
            ], 400); // Retorna 400 Bad Request para Angular
        }

        // CASO C: Error real e interno de infraestructura (ej. SMTP caído)
        return response()->json([
            'status' => 'error',
            'message' => 'No se pudo enviar el correo de restablecimiento en este momento.'
        ], 500);
    }

    /**
     * PASO 2: Procesar el formulario de restablecimiento que viene desde el mail
     * Ruta: /api/v1/auth/password-update
     */
    public function updatePassword(Request $request)
    {
        $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => 'required|min:6',
            'password_confirmation' => 'required|same:password',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'No se encontró ningún usuario con este correo electrónico.'
            ], 442);
        }

        // =========================================================================
        // DOBLE CONTROL DE TIEMPO (POR SEGURIDAD EN EL FORMULARIO)
        // =========================================================================
        if ($user->password_changed_at) {
            $fechaCambio = Carbon::parse($user->password_changed_at);

            if ($fechaCambio->greaterThanOrEqualTo(Carbon::now()->subMinute())) {
                $segundosRestantes = Carbon::now()->diffInSeconds($fechaCambio->addMinute(), false);
                $segundosRestantes = $segundosRestantes > 0 ? $segundosRestantes : 1;

                return response()->json([
                    'status' => 'error',
                    'message' => "Debes esperar 1 minuto entre cambios de contraseña. Inténtalo de nuevo en {$segundosRestantes} segundos."
                ], 400);
            }
        }
        // =========================================================================

        // Validar el token en la tabla password_reset_tokens
        $tokenRecord = DB::table('password_reset_tokens')->where('email', $request->email)->first();

        if (!$tokenRecord || !Hash::check($request->token, $tokenRecord->token)) {
            return response()->json([
                'status' => 'error',
                'message' => 'La solicitud de restablecimiento no es válida o ya expiró.'
            ], 400);
        }

        // Actualizamos la contraseña y registramos el timestamp de cambio en PostgreSQL
        $user->update([
            'password' => $request->password,
            'password_changed_at' => Carbon::now()->toDateTimeString()
        ]);

        // Eliminar token usado
        DB::table('password_reset_tokens')->where('email', $request->email)->delete();

        return response()->json([
            'status' => 'success',
            'message' => '¡Tu contraseña ha sido actualizada con éxito! Ya podés iniciar sesión.'
        ], 200);
    }
}
