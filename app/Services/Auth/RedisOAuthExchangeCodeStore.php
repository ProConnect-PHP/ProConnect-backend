<?php

namespace App\Services\Auth;

use App\Contracts\Auth\IOAuthExchangeCodeStore;
use Illuminate\Support\Facades\Cache;

final class RedisOAuthExchangeCodeStore implements IOAuthExchangeCodeStore
{
    private const TTL_SECONDS = 120;

    public function put(string $code, string $userId, string $provider): void
    {
        $expiresAt = now()->addSeconds(self::TTL_SECONDS);

        Cache::put(
            key: $this->key($code),
            value: [
                'user_id' => $userId,
                'provider' => $provider,
                'expires_at' => $expiresAt->getTimestamp(),
            ],
            ttl: self::TTL_SECONDS,
        );
    }

    public function pull(string $code): ?array
    {
        $payload = Cache::lock($this->lockKey($code), 5)->get(
            fn () => Cache::pull($this->key($code))
        );

        if (! is_array($payload)) {
            return null;
        }

        $expiresAt = $payload['expires_at'] ?? null;

        if (! is_int($expiresAt) || now()->getTimestamp() >= $expiresAt) {
            return null;
        }

        return $payload;
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
