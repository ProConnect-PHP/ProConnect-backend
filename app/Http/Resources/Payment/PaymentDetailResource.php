<?php

namespace App\Http\Resources\Payment;

use App\Models\Payment\Payment;
use App\Models\Payment\PaymentIntent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

class PaymentDetailResource extends JsonResource
{
    /** @param Collection<int, PaymentIntent> $relatedAttempts */
    public function __construct(Payment $payment, private readonly Collection $relatedAttempts)
    {
        parent::__construct($payment);
    }

    public function toArray(Request $request): array
    {
        /** @var Payment $payment */
        $payment = $this->resource;

        return [
            'payment' => (new PaymentResource($payment))->resolve($request),
            'booking' => $payment->booking ? [
                'id' => $payment->booking->id,
                'status' => $payment->booking->status?->value ?? $payment->booking->status,
                'starts_at' => $payment->booking->starts_at?->toDateTimeString(),
                'ends_at' => $payment->booking->ends_at?->toDateTimeString(),
                'service_id' => $payment->booking->service_id,
            ] : null,
            'package_product' => $payment->packageProduct ? [
                'id' => $payment->packageProduct->id,
                'name' => $payment->packageProduct->name,
                'sessions_count' => $payment->packageProduct->sessions_count,
                'service_id' => $payment->packageProduct->service_id,
            ] : null,
            'successful_intent' => $payment->intent
                ? (new PaymentIntentResource($payment->intent))->resolve($request)
                : null,
            'related_attempts' => $this->relatedAttempts
                ->map(
                    fn (PaymentIntent $attempt): array => (new PaymentIntentResource($attempt))
                        ->resolve($request)
                )
                ->values()
                ->all(),
        ];
    }
}
