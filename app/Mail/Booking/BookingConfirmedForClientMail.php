<?php

namespace App\Mail\Booking;

use App\Models\Booking\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BookingConfirmedForClientMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly Booking $booking
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Tu reserva fue confirmada'
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.booking.confirmed-for-client',
            with: [
                'booking' => $this->booking,
            ],
        );
    }
}
