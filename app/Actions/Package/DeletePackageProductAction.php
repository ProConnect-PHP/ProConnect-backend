<?php

namespace App\Actions\Package;

use App\Models\Package\ClientPackage;
use App\Models\Package\PackageProduct;
use App\Support\ActivityLog\ActivityLogActorMode;
use App\Support\ActivityLog\ActivityLogEvent;
use App\Support\ActivityLog\ActivityLogger;

class DeletePackageProductAction
{
    public function __construct(
        private readonly ActivityLogger $activityLogger,
    ) {}

    public function __invoke(PackageProduct $packageProduct): void
    {
        $hasBeenPurchased = ClientPackage::query()
            ->where('package_product_id', $packageProduct->id)
            ->exists();

        if ($hasBeenPurchased) {
            $packageProduct->update(['is_active' => false]);
            $this->logDeletion($packageProduct, 'deactivated');

            return;
        }

        $packageProduct->delete();
        $this->logDeletion($packageProduct, 'deleted');
    }

    private function logDeletion(PackageProduct $packageProduct, string $result): void
    {
        $this->activityLogger->record(
            event: ActivityLogEvent::PackageProductDeleted,
            entityType: 'package_product',
            entityId: $packageProduct->id,
            entityOwnerId: $packageProduct->professional_id,
            metadata: [
                'package_product_id' => $packageProduct->id,
                'professional_id' => $packageProduct->professional_id,
                'result' => $result,
            ],
            actingAs: ActivityLogActorMode::Professional,
        );
    }
}
