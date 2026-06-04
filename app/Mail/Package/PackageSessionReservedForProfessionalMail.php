<?php

namespace App\Mail\Package;

use App\Models\Package\PackageSession;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PackageSessionReservedForProfessionalMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly PackageSession $packageSession
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Nueva reserva usando paquete'
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.package.session-reserved-for-professional',
            with: [
                'packageSession' => $this->packageSession,
            ],
        );
    }
}
