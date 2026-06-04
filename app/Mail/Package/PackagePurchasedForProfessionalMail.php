<?php

namespace App\Mail\Package;

use App\Models\Package\ClientPackage;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PackagePurchasedForProfessionalMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly ClientPackage $clientPackage
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Compraron uno de tus paquetes'
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.package.purchased-for-professional',
            with: [
                'clientPackage' => $this->clientPackage,
            ],
        );
    }
}
