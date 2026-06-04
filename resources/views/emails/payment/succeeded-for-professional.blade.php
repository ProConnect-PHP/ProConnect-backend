<x-mail::message>
# Recibiste un pago

Se confirmo un pago para una de tus reservas.

## Detalles

**Reserva:** {{ $payment->booking_id }}  
**Monto:** {{ $payment->currency }} {{ number_format($payment->amount, 0, ',', '.') }}  
**Fecha:** {{ $payment->paid_at?->format('d/m/Y H:i') }}

<x-mail::button :url="config('proconnect.frontend_url', config('app.url')) . '/professional/bookings/' . $payment->booking_id">
Ver reserva
</x-mail::button>

Gracias,  
{{ config('app.name') }}
</x-mail::message>
