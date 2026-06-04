<?php

namespace App\Listeners\Package;

use App\Actions\Notification\SendPackageEmailNotificationOnceAction;
use App\Events\Package\PackageSessionReserved;
use App\Mail\Package\PackageSessionReservedForClientMail;
use App\Mail\Package\PackageSessionReservedForProfessionalMail;
use App\Models\Package\PackageSession;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendPackageSessionReservedNotifications implements ShouldQueue
{
    public string $queue = 'emails';

    public bool $afterCommit = true;

    public function handle(PackageSessionReserved $event): void
    {
        $packageSession = PackageSession::query()
            ->with([
                'client',
                'professional.user',
                'clientPackage.packageProduct.service',
                'clientPackage.service',
                'booking.service',
            ])
            ->findOrFail($event->packageSessionId);

        $sendOnce = app(SendPackageEmailNotificationOnceAction::class);

        if ($packageSession->client) {
            $sendOnce(
                recipient: $packageSession->client,
                mailable: new PackageSessionReservedForClientMail($packageSession),
                type: 'package_session_reserved_client',
                clientPackage: $packageSession->clientPackage,
                packageSession: $packageSession,
                booking: $packageSession->booking,
            );
        }

        $professionalUser = $packageSession->professional?->user;

        if (! $professionalUser) {
            return;
        }

        $sendOnce(
            recipient: $professionalUser,
            mailable: new PackageSessionReservedForProfessionalMail($packageSession),
            type: 'package_session_reserved_professional',
            clientPackage: $packageSession->clientPackage,
            packageSession: $packageSession,
            booking: $packageSession->booking,
        );
    }
}
