<?php

namespace App\Enums\Auth;

enum OAuthProvider: string
{
    case Google = 'google';
    case GitHub = 'github';

    public static function values(): array
    {
        return array_map(
            static fn (self $provider): string => $provider->value,
            self::cases()
        );
    }

    public static function isSupported(string $provider): bool
    {
        return in_array($provider, self::values(), true);
    }
}
