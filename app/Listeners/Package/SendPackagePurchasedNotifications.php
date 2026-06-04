<?php

namespace App\Listeners\Package;

use App\Actions\Notification\SendPackageEmailNotificationOnceAction;
use App\Events\Package\PackagePurchased;
use App\Mail\Package\PackagePurchasedForClientMail;
use App\Mail\Package\PackagePurchasedForProfessionalMail;
use App\Models\Package\ClientPackage;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendPackagePurchasedNotifications implements ShouldQueue
{
    public string $queue = 'emails';

    public bool $afterCommit = true;

    public function handle(PackagePurchased $event): void
    {
        $clientPackage = ClientPackage::query()
            ->with([
                'client',
                'professional.user',
                'packageProduct.service',
                'service',
            ])
            ->findOrFail($event->clientPackageId);

        $sendOnce = app(SendPackageEmailNotificationOnceAction::class);

        if ($clientPackage->client) {
            $sendOnce(
                recipient: $clientPackage->client,
                mailable: new PackagePurchasedForClientMail($clientPackage),
                type: 'package_purchased_client',
                clientPackage: $clientPackage,
                payload: ['package_product_id' => $clientPackage->package_product_id],
            );
        }

        $professionalUser = $clientPackage->professional?->user;

        if (! $professionalUser) {
            return;
        }

        $sendOnce(
            recipient: $professionalUser,
            mailable: new PackagePurchasedForProfessionalMail($clientPackage),
            type: 'package_purchased_professional',
            clientPackage: $clientPackage,
            payload: ['package_product_id' => $clientPackage->package_product_id],
        );
    }
}
