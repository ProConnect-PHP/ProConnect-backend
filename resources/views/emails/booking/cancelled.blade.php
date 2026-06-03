<x-mail::message>
# Reserva cancelada

Hola,

La reserva para **{{ $booking->service?->name }}** fue cancelada.

@if($actor)
**Cancelada por:** {{ $actor->name }}
@endif

## Detalles

**Fecha:** {{ $booking->starts_at->format('d/m/Y') }}  
**Horario:** {{ $booking->starts_at->format('H:i') }} - {{ $booking->ends_at->format('H:i') }}  
**Modalidad:** {{ ucfirst($booking->modality) }}

@if ($booking->cancellation_reason)
**Motivo:** {{ $booking->cancellation_reason }}
@endif

<x-mail::button :url="config('proconnect.frontend_url', config('app.url')) . '/services'">
Explorar otros servicios
</x-mail::button>

Gracias,  
{{ config('app.name') }}
</x-mail::message>
