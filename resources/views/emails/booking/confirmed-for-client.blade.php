<x-mail::message>
# Reserva confirmada

Hola {{ $booking->client->name }},

Tu reserva fue confirmada por el profesional.

## Detalles de la reserva

**Servicio:** {{ $booking->service?->name }}  
**Profesional:** {{ $booking->professional?->user?->name }}  
**Fecha:** {{ $booking->starts_at->format('d/m/Y') }}  
**Horario:** {{ $booking->starts_at->format('H:i') }} - {{ $booking->ends_at->format('H:i') }}  
**Modalidad:** {{ ucfirst($booking->modality) }}  
**Precio:** ${{ number_format((float) $booking->price_snapshot, 0, ',', '.') }}

@if(\App\Support\Booking\BookingLocationPresenter::hasPhysicalLocation($booking))
## Ubicacion

**Direccion:** {{ \App\Support\Booking\BookingLocationPresenter::address($booking) }}

@php
    $mapImageUrl = \App\Support\Booking\BookingLocationPresenter::staticMapImageUrl($booking);
    $mapUrl = \App\Support\Booking\BookingLocationPresenter::mapUrl($booking);
@endphp

@if($mapImageUrl && $mapUrl)
[![Mapa de ubicacion]({{ $mapImageUrl }})]({{ $mapUrl }})
@endif

@if($mapUrl)
<x-mail::button :url="$mapUrl">
Ver ubicacion en mapa
</x-mail::button>
@endif
@else
## Modalidad remota

Esta reserva es remota. El enlace de videollamada estara disponible cuando se habilite la integracion correspondiente.
@endif

<x-mail::button :url="config('proconnect.frontend_url', config('app.url')) . '/my-bookings/' . $booking->id">
Ver reserva
</x-mail::button>

Gracias,  
{{ config('app.name') }}
</x-mail::message>
