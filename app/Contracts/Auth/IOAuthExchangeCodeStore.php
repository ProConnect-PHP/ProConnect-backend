<?php

namespace App\Contracts\Auth;

interface IOAuthExchangeCodeStore
{
    public function put(string $code, string $userId, string $provider): void;

    /**
     * @return array{user_id: string, provider: string, expires_at: int}|null
     */
    public function pull(string $code): ?array;
}
