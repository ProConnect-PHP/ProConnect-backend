<x-mail::message>
# Nueva reserva pendiente

Hola {{ $booking->professional?->user?->name }},

Recibiste una nueva reserva que requiere tu confirmacion.

## Detalles

**Servicio:** {{ $booking->service?->name }}  
**Cliente:** {{ $booking->client?->name }}  
**Fecha:** {{ $booking->starts_at->format('d/m/Y') }}  
**Horario:** {{ $booking->starts_at->format('H:i') }} - {{ $booking->ends_at->format('H:i') }}  
**Modalidad:** {{ ucfirst($booking->modality) }}  
**Precio:** ${{ number_format((float) $booking->price_snapshot, 0, ',', '.') }}

@if(\App\Support\Booking\BookingLocationPresenter::hasPhysicalLocation($booking))
## Ubicacion

**Direccion:** {{ \App\Support\Booking\BookingLocationPresenter::address($booking) }}

@php
    $mapUrl = \App\Support\Booking\BookingLocationPresenter::mapUrl($booking);
@endphp

@if($mapUrl)
<x-mail::button :url="$mapUrl">
Ver ubicacion
</x-mail::button>
@endif
@endif

<x-mail::button :url="config('proconnect.frontend_url', config('app.url')) . '/professional/bookings/' . $booking->id">
Ver y gestionar reserva
</x-mail::button>

Gracias,  
{{ config('app.name') }}
</x-mail::message>
