<?php

namespace App\Http\Requests\Public;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ListPublicServicesRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (! $this->has('is_verified')) {
            return;
        }

        $value = $this->input('is_verified');

        if (is_string($value)) {
            $normalized = match (strtolower($value)) {
                'true' => true,
                'false' => false,
                default => $value,
            };

            $this->merge([
                'is_verified' => $normalized,
            ]);
        }
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'modality' => ['nullable', Rule::in(['presencial', 'remota', 'hibrida'])],
            'min_price' => ['nullable', 'numeric', 'min:0'],
            'max_price' => ['nullable', 'numeric', 'min:0', 'gte:min_price'],
            'duration_minutes' => ['nullable', 'integer', Rule::in([15, 30, 45, 60, 90, 120])],
            'available_date' => ['nullable', 'date'],
            'is_verified' => ['nullable', 'boolean'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'radius_km' => ['nullable', 'numeric', 'min:1', 'max:500'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
            'page' => ['nullable', 'integer', 'min:1'],
            'sort' => [
                'nullable',
                Rule::in([
                    'recent',
                    'price_asc',
                    'price_desc',
                    'duration_asc',
                    'duration_desc',
                    'rating_desc',
                ]),
            ],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $geoFields = ['latitude', 'longitude', 'radius_km'];
            $presentFields = array_filter(
                $geoFields,
                fn (string $field): bool => $this->hasFilledValue($field)
            );

            if (count($presentFields) === 0 || count($presentFields) === count($geoFields)) {
                return;
            }

            foreach (array_diff($geoFields, $presentFields) as $field) {
                $validator->errors()->add(
                    $field,
                    'The latitude, longitude and radius_km fields must be provided together.'
                );
            }
        });
    }

    private function hasFilledValue(string $field): bool
    {
        return $this->has($field)
            && $this->input($field) !== null
            && $this->input($field) !== '';
    }
}
