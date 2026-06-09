@if($booking->videoSession)
@php
    $videoSessionUrl = config('proconnect.frontend_url', config('app.url')) . '/video-sessions/' . $booking->videoSession->id;
    $joinAvailableAt = $booking->starts_at->copy()->subMinutes(config('proconnect.video.join_before_minutes', 15));
@endphp

## Sesion virtual

Esta reserva incluye una sesion virtual.

**Sala:** {{ $booking->videoSession->room_name }}

<x-mail::button :url="$videoSessionUrl">
Unirme a la sesion
</x-mail::button>

Podras unirte desde las {{ $joinAvailableAt->format('H:i') }}.
@endif
