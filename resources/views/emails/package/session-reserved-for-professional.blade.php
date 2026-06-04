<x-mail::message>
# Nueva reserva usando paquete

Hola {{ $packageSession->professional?->user?->name }},

{{ $packageSession->client?->name }} realizó una reserva usando un paquete de sesiones.

## Reserva

**Cliente:** {{ $packageSession->client?->name }}  
**Servicio:** {{ $packageSession->booking?->service?->name }}  
**Fecha:** {{ $packageSession->booking?->starts_at?->format('d/m/Y') }}  
**Horario:** {{ $packageSession->booking?->starts_at?->format('H:i') }} - {{ $packageSession->booking?->ends_at?->format('H:i') }}

## Paquete

**Paquete:** {{ $packageSession->clientPackage?->packageProduct?->name }}  
**Sesiones totales:** {{ $packageSession->clientPackage?->total_sessions }}  
**Sesiones usadas:** {{ $packageSession->clientPackage?->used_sessions }}  
**Sesiones restantes:** {{ $packageSession->clientPackage?->remainingSessions() }}

@if($packageSession->clientPackage?->expires_at)
**Válido hasta:** {{ $packageSession->clientPackage->expires_at->format('d/m/Y') }}
@endif

<x-mail::button :url="config('proconnect.frontend_url', config('app.url')) . '/professional/bookings/' . $packageSession->booking_id">
Ver reserva
</x-mail::button>

<x-mail::button :url="config('proconnect.frontend_url', config('app.url')) . '/professional/client-packages/' . $packageSession->client_package_id">
Ver paquete vendido
</x-mail::button>

Gracias,  
{{ config('app.name') }}
</x-mail::message>
