<?php

namespace App\Models\Payment;

use App\Enums\Payment\PayableType;
use App\Enums\Payment\PaymentIntentStatus;
use App\Enums\Payment\PaymentProvider;
use App\Models\Booking\Booking;
use App\Models\Package\PackageProduct;
use App\Models\User\ProfessionalProfile;
use App\Models\User\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'booking_id',
    'package_product_id',
    'payable_type',
    'payable_id',
    'client_id',
    'professional_id',
    'provider',
    'status',
    'amount',
    'currency',
    'provider_reference',
    'checkout_url',
    'metadata',
    'expires_at',
    'processing_at',
    'succeeded_at',
    'failed_at',
    'cancelled_at',
    'failure_reason',
])]
class PaymentIntent extends Model
{
    use HasFactory;
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected function casts(): array
    {
        return [
            'provider' => PaymentProvider::class,
            'status' => PaymentIntentStatus::class,
            'payable_type' => PayableType::class,
            'amount' => 'integer',
            'metadata' => 'array',
            'expires_at' => 'datetime',
            'processing_at' => 'datetime',
            'succeeded_at' => 'datetime',
            'failed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function packageProduct(): BelongsTo
    {
        return $this->belongsTo(PackageProduct::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function professional(): BelongsTo
    {
        return $this->belongsTo(ProfessionalProfile::class, 'professional_id');
    }

    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class);
    }

    public function isPending(): bool
    {
        return $this->status === PaymentIntentStatus::Pending;
    }

    public function isProcessing(): bool
    {
        return $this->status === PaymentIntentStatus::Processing;
    }

    public function hasCheckout(): bool
    {
        return $this->status === PaymentIntentStatus::CheckoutCreated
            && is_string($this->checkout_url)
            && $this->checkout_url !== '';
    }

    public function isSucceeded(): bool
    {
        return $this->status === PaymentIntentStatus::Succeeded;
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }
}
