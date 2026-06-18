<?php

namespace App\Http\Resources\User;

use BackedEnum;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role instanceof BackedEnum ? $this->role->value : $this->role,
            'status' => $this->status ?? 'active',
            'avatar_url' => $this->avatar_url, // Luego se hara con el $disk del FileSystem public de laravel
            'has_professional_profile' => $this->professionalProfile()->exists(),
        ];
    }
}
