<x-mail::message>
# Paquete adquirido correctamente

Hola {{ $clientPackage->client?->name }},

Tu paquete fue adquirido correctamente.

## Detalles del paquete

**Paquete:** {{ $clientPackage->packageProduct?->name }}  
**Profesional:** {{ $clientPackage->professional?->user?->name }}  
@if($clientPackage->service)
**Servicio:** {{ $clientPackage->service?->name }}  
@endif
**Sesiones incluidas:** {{ $clientPackage->total_sessions }}  
**Sesiones disponibles:** {{ $clientPackage->remainingSessions() }}  
**Precio:** {{ $clientPackage->currency }} {{ number_format($clientPackage->price_snapshot, 0, ',', '.') }}  
**Fecha de compra:** {{ $clientPackage->purchased_at?->format('d/m/Y H:i') }}  

@if($clientPackage->expires_at)
**Válido hasta:** {{ $clientPackage->expires_at->format('d/m/Y') }}
@else
**Vigencia:** Sin vencimiento definido
@endif

<x-mail::button :url="config('proconnect.frontend_url', config('app.url')) . '/my-packages/' . $clientPackage->id">
Ver mi paquete
</x-mail::button>

Gracias,  
{{ config('app.name') }}
</x-mail::message>
