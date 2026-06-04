<?php

namespace App\Mail\Payment;

use App\Models\Payment\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PaymentSucceededForClientMail extends Mailable
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
            subject: 'Pago confirmado'
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.payment.succeeded-for-client',
            with: [
                'payment' => $this->payment,
            ],
        );
    }
}
