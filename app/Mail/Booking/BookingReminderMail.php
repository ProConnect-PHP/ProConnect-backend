<?php

namespace App\Mail\Booking;

use App\Models\Booking\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BookingReminderMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly Booking $booking,
        public readonly string $reminderType = 'booking_reminder_24h'
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: match ($this->reminderType) {
                'booking_reminder_1h' => 'Tu reserva empieza en 1 hora',
                'booking_reminder_soon' => 'Tu reserva empieza pronto',
                default => 'Recordatorio de reserva',
            }
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.bookirng.reminder',
            with: [
                'booking' => $this->booking,
                'reminderType' => $this->reminderType,
            ],
        );
    }
}
