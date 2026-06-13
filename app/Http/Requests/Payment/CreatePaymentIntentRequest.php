<?php

namespace App\Http\Requests\Payment;

use App\Enums\Payment\PaymentProvider;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreatePaymentIntentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'provider' => ['nullable', Rule::enum(PaymentProvider::class)],
            'amount' => ['nullable', 'numeric'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
