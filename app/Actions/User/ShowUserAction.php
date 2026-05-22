<?php

namespace App\Actions\User;

use App\Models\User\User;

class ShowUserAction
{
    public function __invoke(): User
    {
        return auth('user_jwt')->user();
    }
}
