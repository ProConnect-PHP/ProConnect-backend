<?php

namespace App\Http\Requests\User;

use App\Enums\UserRole;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => 'sometimes|required|string|max:255',
            'email' => ['sometimes', 'required', 'string', 'email', 'max:255',
                Rule::unique('users', 'email')->ignore($this->user()->id),
            ],
            'password' => 'sometimes|required|string|min:8|confirmed',
            'role' => [
                'sometimes',
                'required',
                'string',
                Rule::enum(UserRole::class),
                function ($attribute, $value, $fail) {
                    if ($this->user()->isProfessional() && $value === UserRole::Client->value) {
                        $fail('No se puede cambiar el rol de profesional a cliente.');
                    }
                },
            ],
            'avatar_url' => 'nullable|url|max:255',
        ];
    }
}
