<?php

namespace App\Support\Security;

use App\Models\User\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class ApiRateLimit
{
    public static function byRole(Request $request, string $limiter): Limit
    {
        $user = self::user($request);
        $role = match (true) {
            $user?->isProfessional() => 'professional',
            $user?->isClient() => 'client',
            default => 'guest',
        };

        return Limit::perMinute(
            (int) config("security.rate_limits.{$limiter}.{$role}")
        )->by(self::key($request, $limiter, $user));
    }

    public static function login(Request $request): Limit
    {
        $email = Str::lower(trim((string) $request->input('email')));
        $identity = $email !== '' ? $email.'|'.$request->ip() : $request->ip();

        return Limit::perMinute(
            (int) config('security.rate_limits.auth_login')
        )->by('auth-login:'.sha1($identity));
    }

    public static function byIp(Request $request, string $limiter): Limit
    {
        return Limit::perMinute(
            (int) config("security.rate_limits.{$limiter}")
        )->by($limiter.':ip:'.$request->ip());
    }

    private static function user(Request $request): ?User
    {
        $user = $request->user('user_jwt');

        return $user instanceof User ? $user : null;
    }

    private static function key(
        Request $request,
        string $limiter,
        ?User $user
    ): string {
        if ($user) {
            return $limiter.':user:'.$user->getAuthIdentifier();
        }

        return $limiter.':ip:'.$request->ip();
    }
}
