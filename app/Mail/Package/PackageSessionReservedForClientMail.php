<?php

namespace App\Mail\Package;

use App\Models\Package\PackageSession;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PackageSessionReservedForClientMail extends Mailable
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
            subject: 'Reserva realizada usando tu paquete'
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.package.session-reserved-for-client',
            with: [
                'packageSession' => $this->packageSession,
            ],
        );
    }
}
