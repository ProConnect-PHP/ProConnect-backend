<?php

namespace App\Events\Package;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PackageSessionReserved
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly string $packageSessionId
    ) {
    }
}
