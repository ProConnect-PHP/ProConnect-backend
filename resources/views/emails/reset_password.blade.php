<x-mail::message>
@php
    $appName = config('app.name', 'ProConnect');
    $passwordBroker = config('auth.defaults.passwords', 'users');
    $expirationMinutes = config("auth.passwords.{$passwordBroker}.expire", 60);
@endphp

# Restablecer contraseña

Hola {{ $name ?? 'usuario' }},

Recibimos una solicitud para restablecer la contraseña de tu cuenta en **{{ $appName }}**.

Si fuiste vos, podés crear una nueva contraseña usando el siguiente botón:

<x-mail::button :url="$resetUrl">
Restablecer contraseña
</x-mail::button>

Este enlace es de uso único y vencerá en **{{ $expirationMinutes }} minutos**.

Si vos no solicitaste este cambio, podés ignorar este correo de forma segura. Tu contraseña actual seguirá siendo válida.

## Enlace alternativo

Si el botón no funciona, copiá y pegá este enlace en tu navegador:

{{ $resetUrl }}

Gracias,
{{ $appName }}
</x-mail::message>
