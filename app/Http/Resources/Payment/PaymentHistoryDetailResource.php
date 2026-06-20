<?php

namespace App\Http\Resources\Payment;

use App\DTOs\Payment\PaymentHistoryItemData;
use App\Models\Payment\PaymentIntent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

class PaymentHistoryDetailResource extends JsonResource
{
    /** @param Collection<int, PaymentIntent> $relatedAttempts */
    public function __construct(
        PaymentHistoryItemData $item,
        private readonly Collection $relatedAttempts,
    ) {
        parent::__construct($item);
    }

    public function toArray(Request $request): array
    {
        /** @var PaymentHistoryItemData $item */
        $item = $this->resource;

        return [
            'item' => (new PaymentHistoryItemResource($item))->resolve($request),
            'booking' => $item->booking,
            'package_product' => $item->packageProduct,
            'related_attempts' => $this->relatedAttempts
                ->map(fn (PaymentIntent $attempt): array => [
                    'id' => $attempt->id,
                    'status' => $attempt->status?->value ?? $attempt->status,
                    'provider' => $attempt->provider?->value ?? $attempt->provider,
                    'provider_reference' => $attempt->provider_reference,
                    'created_at' => $attempt->created_at?->toDateTimeString(),
                ])
                ->values()
                ->all(),
        ];
    }
}
