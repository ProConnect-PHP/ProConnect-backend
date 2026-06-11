<?php

namespace App\Actions\ProfessionalProfile;

use App\Enums\UserRole;
use App\Exceptions\ApiException;
use App\Http\Requests\ProfessionalProfile\StoreProfessionalProfileRequest;
use App\Models\User\ProfessionalProfile;
use App\Models\User\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class StoreProfessionalProfileAction
{
    public function __invoke(
        StoreProfessionalProfileRequest $request
    ): ProfessionalProfile {
        $user = auth('user_jwt')->user();

        if (! $user instanceof User) {
            throw new AuthenticationException;
        }

        return DB::transaction(function () use ($request, $user): ProfessionalProfile {
            $user = User::query()
                ->whereKey($user->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (ProfessionalProfile::withTrashed()->where('user_id', $user->id)->exists()) {
                throw new ApiException(
                    error: 'ProfessionalProfileAlreadyExists',
                    message: 'El perfil profesional ya existe para este usuario.',
                    status: Response::HTTP_CONFLICT
                );
            }

            $profile = ProfessionalProfile::create([
                'user_id' => $user->id,
                'bio' => $request->validated('bio'),
            ]);

            if ($user->role === UserRole::Client) {
                $user->forceFill([
                    'role' => UserRole::Professional,
                ])->save();
            }

            return $profile->refresh();
        });
    }
}
