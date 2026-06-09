<x-mail::message>
@php
    $reminderType = $reminderType ?? 'booking_reminder_24h';

    $title = match ($reminderType) {
        'booking_reminder_1h' => 'Tu reserva empieza en 1 hora',
        'booking_reminder_soon' => 'Tu reserva empieza pronto',
        default => 'Recordatorio de reserva',
    };

    $intro = match ($reminderType) {
        'booking_reminder_1h' => 'Te recordamos que tenés una reserva que empieza aproximadamente en 1 hora.',
        'booking_reminder_soon' => 'Te recordamos que tenés una reserva próxima a comenzar.',
        default => 'Te recordamos que tenés una reserva próxima.',
    };

    $frontendBookingUrl = config('proconnect.frontend_url', config('app.url')) . '/my-bookings/' . $booking->id;
@endphp

# {{ $title }}

Hola,

{{ $intro }}

## Detalles

**Servicio:** {{ $booking->service?->name }}
**Fecha:** {{ $booking->starts_at->format('d/m/Y') }}
**Horario:** {{ $booking->starts_at->format('H:i') }} - {{ $booking->ends_at->format('H:i') }}
**Modalidad:** {{ ucfirst($booking->modality) }}

@if(\App\Support\Booking\BookingLocationPresenter::hasPhysicalLocation($booking))
## Ubicación

**Dirección:** {{ \App\Support\Booking\BookingLocationPresenter::address($booking) }}

@php
    $mapImageUrl = \App\Support\Booking\BookingLocationPresenter::staticMapImageUrl($booking);
    $mapUrl = \App\Support\Booking\BookingLocationPresenter::mapUrl($booking);
@endphp

@if($mapImageUrl && $mapUrl)
[![Mapa de ubicación]({{ $mapImageUrl }})]({{ $mapUrl }})
@endif

@if($mapUrl)
<x-mail::button :url="$mapUrl">
Ver ubicación en mapa
</x-mail::button>
@endif
@endif

@include('emails.booking.video-session', ['booking' => $booking])

@if(! \App\Support\Booking\BookingLocationPresenter::hasPhysicalLocation($booking) && ! $booking->videoSession)
## Modalidad remota

Esta reserva es remota. Revisá el detalle de la reserva para acceder a la información disponible.
@endif

<x-mail::button :url="$frontendBookingUrl">
Ver reserva
</x-mail::button>

Gracias,
{{ config('app.name') }}
</x-mail::message>
