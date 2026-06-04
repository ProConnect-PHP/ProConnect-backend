<?php

namespace App\Events\Package;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PackagePurchased
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly string $clientPackageId
    ) {
    }
}
