<?php

namespace App\Actions\User;

use App\Http\Requests\User\StoreUserRequest;
use App\Models\User\User;

class StoreUserAction
{
    public function __invoke(StoreUserRequest $request): User
    {
        return User::create($request->validated());
    }
}
