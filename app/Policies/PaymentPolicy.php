<?php

namespace App\Policies;

use App\Models\Payment\Payment;
use App\Models\User\User;

class PaymentPolicy
{
    public function view(User $user, Payment $payment): bool
    {
        return $payment->client_id === $user->id
            || $user->professionalProfile?->id === $payment->professional_id;
    }
}
