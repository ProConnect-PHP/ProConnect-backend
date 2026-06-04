<?php

namespace App\Policies;

use App\Models\Payment\PaymentIntent;
use App\Models\User\User;

class PaymentIntentPolicy
{
    public function view(User $user, PaymentIntent $intent): bool
    {
        return $intent->client_id === $user->id
            || $user->professionalProfile?->id === $intent->professional_id;
    }

    public function simulate(User $user, PaymentIntent $intent): bool
    {
        return $intent->client_id === $user->id;
    }
}
