<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Restablecer Contraseña</title>
    <style>
        body { font-family: "Helvetica Neue", Helvetica, Arial, sans-serif; background-color: #f4f6f9; color: #333333; margin: 0; padding: 40px 0; }
        .container { max-width: 550px; margin: 0 auto; background: #ffffff; border-radius: 6px; padding: 30px; box-shadow: 0 4px 10px rgba(0,0,0,0.05); }
        .logo { font-size: 24px; font-weight: bold; color: #1e3a8a; text-align: center; margin-bottom: 25px; }
        .button-container { text-align: center; margin: 30px 0; }
        .btn { background-color: #4f46e5; color: #ffffff !important; text-decoration: none; padding: 12px 25px; border-radius: 6px; font-weight: bold; display: inline-block; }
        .footer { text-align: center; font-size: 12px; color: #9ca3af; margin-top: 30px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo">ProConnect</div>
        <h2>¡Hola, {{ $name }}!</h2>
        <p>Recibimos una solicitud para restablecer la contraseña de tu cuenta en ProConnect. No te preocupes, podés hacerlo de forma segura haciendo clic en el siguiente botón:</p>
        
        <div class="button-container">
            <a href="{{ $resetUrl }}" class="btn">Restablecer Contraseña</a>
        </div>
        
        <p>Si el botón no funciona, también podés copiar y pegar este enlace en tu navegador:</p>
        <p style="word-break: break-all; font-size: 12px; color: #6366f1;">{{ $resetUrl }}</p>
        
        <hr style="border: 0; border-top: 1px solid #e5e7eb; margin: 25px 0;">
        <p style="font-size: 13px; color: #6b7280;">Si vos no solicitaste este cambio, podés ignorar este correo de forma segura. Tu contraseña seguirá siendo la misma.</p>
    </div>
    <div class="footer">
        © {{ date("Y") }} ProConnect. Todos los derechos reservados.
    </div>
</body>
</html>
