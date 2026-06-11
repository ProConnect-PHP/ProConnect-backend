<?php

namespace App\Contracts\Auth;

use App\DTO\Auth\SocialUserData;


interface IOAuthIdentityProvider
{
    public function redirectUrl(string $provider): string;

    public function userFromCallback(string $provider): SocialUserData;
}
