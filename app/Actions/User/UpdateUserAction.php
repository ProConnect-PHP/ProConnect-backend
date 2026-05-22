<?php

namespace App\Actions\User;

use App\Http\Requests\User\UpdateUserRequest;
use App\Models\User\User;

class UpdateUserAction
{
    /**
     * Create a new class instance.
     */
    public function __invoke(UpdateUserRequest $request): User
    {
        /** @var \App\Models\User\User $user */
        $user = auth('user_jwt')->user();
        $user->update($request->validated());
        return $user;
    }
}
