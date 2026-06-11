<?php

namespace App\DTO\Auth;

final readonly class SocialUserData
{
    public function __construct(
        public string $provider,
        public string $providerUserId,
        public ?string $email,
        public ?string $name,
        public ?string $avatarUrl,
    ) {
    }
}
