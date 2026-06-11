<?php

namespace App\Actions\Auth;

use App\Contracts\Auth\IOAuthExchangeCodeStore;
use App\Http\Resources\User\UserResource;
use App\Models\User\User;
use App\Services\Auth\AuthTokenIssuer;
use Illuminate\Auth\AuthenticationException;

final class ExchangeOAuthCodeAction
{
    public function __construct(
        private readonly IOAuthExchangeCodeStore $exchangeCodeStore,
        private readonly AuthTokenIssuer $tokenIssuer,
    ) {}

    /**
     * @return array{
     *     access_token: string,
     *     refresh_token: string,
     *     token_type: string,
     *     expires_in: int,
     *     user: UserResource
     * }
     *
     * @throws AuthenticationException
     */
    public function execute(string $code): array
    {
        $payload = $this->exchangeCodeStore->pull($code);

        if (! $payload) {
            throw new AuthenticationException('Invalid or expired OAuth exchange code.');
        }

        $user = User::query()->find($payload['user_id']);

        if (! $user) {
            throw new AuthenticationException('Invalid or expired OAuth exchange code.');
        }

        return $this->tokenIssuer->issueForUser($user);
    }
}
