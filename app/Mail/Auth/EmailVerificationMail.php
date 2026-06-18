<?php

namespace App\Mail\Auth;

use App\Models\User\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

class EmailVerificationMail extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly User $user,
        public readonly string $verificationUrl,
        public readonly Carbon $expiresAt,
    ) {
        $this->onQueue('emails');
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Verifica tu correo en ProConnect'
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.auth.email-verification',
            with: [
                'user' => $this->user,
                'verificationUrl' => $this->verificationUrl,
                'expiresAt' => $this->expiresAt,
            ],
        );
    }
}
