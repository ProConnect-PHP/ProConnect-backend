<?php

namespace App\Mail\Booking;

use App\Models\Booking\Booking;
use App\Models\User\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BookingRescheduledMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly Booking $booking,
        public readonly ?User $actor = null
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Reserva reprogramada'
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.booking.rescheduled',
            with: [
                'booking' => $this->booking,
                'actor' => $this->actor,
            ],
        );
    }
}
