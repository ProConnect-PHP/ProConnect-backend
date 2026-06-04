<?php

namespace App\Mail\Payment;

use App\Models\Payment\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PaymentSucceededForProfessionalMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly Payment $payment
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Recibiste un pago'
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.payment.succeeded-for-professional',
            with: [
                'payment' => $this->payment,
            ],
        );
    }
}
