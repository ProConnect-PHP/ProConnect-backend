<?php

namespace App\Models\Payment;

use App\Enums\Payment\PaymentProvider;
use App\Enums\Payment\PaymentWebhookEventStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'provider',
    'provider_event_id',
    'idempotency_key',
    'event_type',
    'resource_type',
    'resource_id',
    'signature_valid',
    'status',
    'payload',
    'failure_reason',
    'processed_at',
])]
class PaymentWebhookEvent extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected function casts(): array
    {
        return [
            'provider' => PaymentProvider::class,
            'signature_valid' => 'boolean',
            'status' => PaymentWebhookEventStatus::class,
            'payload' => 'array',
            'processed_at' => 'datetime',
        ];
    }
}
