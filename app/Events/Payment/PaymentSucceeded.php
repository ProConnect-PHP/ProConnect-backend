<?php

namespace App\Events\Payment;

use App\Models\Payment\Payment;
use App\Support\ActivityLog\ActivityLogActorMode;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PaymentSucceeded
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Payment $payment,
        public readonly ActivityLogActorMode $actingAs = ActivityLogActorMode::Client,
    ) {}
}
