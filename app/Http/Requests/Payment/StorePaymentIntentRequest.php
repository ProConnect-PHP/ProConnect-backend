<?php

namespace App\Http\Requests\Payment;

use App\Enums\Payment\PayableType;
use App\Enums\Payment\PaymentProvider;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePaymentIntentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'payable_type' => ['required', Rule::enum(PayableType::class)],
            'payable_id' => ['required', 'uuid'],
            'provider' => ['nullable', Rule::enum(PaymentProvider::class)],
            'amount' => ['nullable', 'numeric'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
