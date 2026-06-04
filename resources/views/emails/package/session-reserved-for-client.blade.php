<x-mail::message>
# Reserva realizada usando tu paquete

Hola {{ $packageSession->client?->name }},

Tu reserva fue creada usando una sesión de tu paquete. No se generó un cobro individual para esta reserva.

## Reserva

**Servicio:** {{ $packageSession->booking?->service?->name }}  
**Fecha:** {{ $packageSession->booking?->starts_at?->format('d/m/Y') }}  
**Horario:** {{ $packageSession->booking?->starts_at?->format('H:i') }} - {{ $packageSession->booking?->ends_at?->format('H:i') }}  
**Estado:** {{ $packageSession->booking?->status instanceof \BackedEnum ? $packageSession->booking?->status->value : $packageSession->booking?->status }}

## Paquete utilizado

**Paquete:** {{ $packageSession->clientPackage?->packageProduct?->name }}  
**Sesiones totales:** {{ $packageSession->clientPackage?->total_sessions }}  
**Sesiones usadas:** {{ $packageSession->clientPackage?->used_sessions }}  
**Sesiones restantes:** {{ $packageSession->clientPackage?->remainingSessions() }}

@if($packageSession->clientPackage?->expires_at)
**Días restantes de vigencia:** {{ now()->diffInDays($packageSession->clientPackage->expires_at, false) }}  
**Válido hasta:** {{ $packageSession->clientPackage->expires_at->format('d/m/Y') }}
@endif

<x-mail::button :url="config('proconnect.frontend_url', config('app.url')) . '/my-bookings/' . $packageSession->booking_id">
Ver reserva
</x-mail::button>

<x-mail::button :url="config('proconnect.frontend_url', config('app.url')) . '/my-packages/' . $packageSession->client_package_id">
Ver paquete
</x-mail::button>

Gracias,  
{{ config('app.name') }}
</x-mail::message>
