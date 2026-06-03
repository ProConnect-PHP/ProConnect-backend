<x-mail::message>
# Reserva reprogramada

Hola,

La reserva para **{{ $booking->service?->name }}** fue reprogramada.

@if($actor)
**Reprogramada por:** {{ $actor->name }}
@endif

## Nuevo horario

**Fecha:** {{ $booking->starts_at->format('d/m/Y') }}  
**Horario:** {{ $booking->starts_at->format('H:i') }} - {{ $booking->ends_at->format('H:i') }}  
**Estado:** Pendiente de confirmacion  
**Modalidad:** {{ ucfirst($booking->modality) }}

@if ($booking->reschedule_reason)
**Motivo:** {{ $booking->reschedule_reason }}
@endif

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

<x-mail::button :url="config('proconnect.frontend_url', config('app.url')) . '/my-bookings/' . $booking->id">
Ver reserva
</x-mail::button>

Gracias,  
{{ config('app.name') }}
</x-mail::message>
