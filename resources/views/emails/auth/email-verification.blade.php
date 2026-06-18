<x-mail::message>
# Verifica tu correo electronico

Hola {{ $user->name ?? 'usuario' }},

Para activar todas las funcionalidades de ProConnect, verifica tu correo electronico.

<x-mail::button :url="$verificationUrl">
Verificar correo
</x-mail::button>

Este enlace vence el {{ $expiresAt->format('d/m/Y H:i') }}.

Si no creaste esta cuenta, ignora este correo.

Gracias,  
{{ config('app.name') }}
</x-mail::message>
