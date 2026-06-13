<?php

namespace App\Events\Package;

use App\Support\ActivityLog\ActivityLogActorMode;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PackagePurchased
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly string $clientPackageId,
        public readonly ActivityLogActorMode $actingAs = ActivityLogActorMode::Client,
    ) {}
}
