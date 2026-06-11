<?php

namespace App\Services\Auth;

use App\Contracts\Auth\IOAuthExchangeCodeStore;
use Illuminate\Support\Facades\Cache;

final class RedisOAuthExchangeCodeStore implements IOAuthExchangeCodeStore
{
    public function put(string $code, string $userId, string $provider): void
    {
        Cache::put(
            key: $this->key($code),
            value: [
                'user_id' => $userId,
                'provider' => $provider,
            ],
            ttl: now()->addMinutes(2),
        );
    }

    public function pull(string $code): ?array
    {
        $payload = Cache::lock($this->lockKey($code), 5)->get(
            fn () => Cache::pull($this->key($code))
        );

        return is_array($payload) ? $payload : null;
    }

    private function key(string $code): string
    {
        return "oauth_exchange:{$code}";
    }

    private function lockKey(string $code): string
    {
        return "oauth_exchange_lock:{$code}";
    }
}
