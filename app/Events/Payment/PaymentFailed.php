<?php

namespace App\Events\Payment;

use App\Models\Payment\PaymentIntent;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PaymentFailed
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly PaymentIntent $paymentIntent
    ) {
    }
}
