<x-mail::message>
# Compraron uno de tus paquetes

Hola {{ $clientPackage->professional?->user?->name }},

Un cliente adquirió uno de tus paquetes.

## Detalles

**Cliente:** {{ $clientPackage->client?->name }}  
**Paquete:** {{ $clientPackage->packageProduct?->name }}  
@if($clientPackage->service)
**Servicio:** {{ $clientPackage->service?->name }}  
@endif
**Sesiones:** {{ $clientPackage->total_sessions }}  
**Importe:** {{ $clientPackage->currency }} {{ number_format($clientPackage->price_snapshot, 0, ',', '.') }}  
**Fecha:** {{ $clientPackage->purchased_at?->format('d/m/Y H:i') }}

@if($clientPackage->expires_at)
**Vence:** {{ $clientPackage->expires_at->format('d/m/Y') }}
@endif

<x-mail::button :url="config('proconnect.frontend_url', config('app.url')) . '/professional/client-packages/' . $clientPackage->id">
Ver paquete vendido
</x-mail::button>

Gracias,  
{{ config('app.name') }}
</x-mail::message>
