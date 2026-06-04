<?php

namespace App\Enums\Payment;

enum PaymentProvider: string
{
    case Simulator = 'simulator';
    case MercadoPago = 'mercadopago';
    case Paypal = 'paypal';
    case Stripe = 'stripe';
}
