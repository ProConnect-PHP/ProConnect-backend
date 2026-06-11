<?php

namespace App\Services\Auth;

use App\Contracts\Auth\IOAuthIdentityProvider;
use App\DTO\Auth\SocialUserData;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\AbstractProvider;

final class SocialiteOAuthIdentityProvider implements IOAuthIdentityProvider
{
    public function redirectUrl(string $provider): string
    {
        /** @var AbstractProvider $socialiteProvider */
        $socialiteProvider = Socialite::driver($provider);

        return $socialiteProvider
            ->stateless()
            ->redirect()
            ->getTargetUrl();
    }

    public function userFromCallback(string $provider): SocialUserData
    {
        /** @var AbstractProvider $socialiteProvider */
        $socialiteProvider = Socialite::driver($provider);

        $socialUser = $socialiteProvider
            ->stateless()
            ->user();

        return new SocialUserData(
            provider: $provider,
            providerUserId: (string) $socialUser->getId(),
            email: $socialUser->getEmail(),
            name: $socialUser->getName() ?: $socialUser->getNickname(),
            avatarUrl: $socialUser->getAvatar(),
        );
    }
}
