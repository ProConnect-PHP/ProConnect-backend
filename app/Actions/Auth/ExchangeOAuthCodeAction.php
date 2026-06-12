<?php

namespace App\Actions\Auth;

use App\Contracts\Auth\IOAuthExchangeCodeStore;
use App\Http\Resources\User\UserResource;
use App\Models\User\User;
use App\Services\Auth\AuthTokenIssuer;
use App\Support\ActivityLog\ActivityLogActorMode;
use App\Support\ActivityLog\ActivityLogEvent;
use App\Support\ActivityLog\ActivityLogger;
use Illuminate\Auth\AuthenticationException;

final class ExchangeOAuthCodeAction
{
    public function __construct(
        private readonly IOAuthExchangeCodeStore $exchangeCodeStore,
        private readonly AuthTokenIssuer $tokenIssuer,
        private readonly ActivityLogger $activityLogger,
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
            $this->logFailure('invalid_or_expired_exchange_code');

            throw new AuthenticationException('Invalid or expired OAuth exchange code.');
        }

        $user = User::query()->find($payload['user_id']);

        if (! $user) {
            $this->logFailure('oauth_user_not_found');

            throw new AuthenticationException('Invalid or expired OAuth exchange code.');
        }

        return $this->tokenIssuer->issueForUser($user);
    }

    private function logFailure(string $reason): void
    {
        $this->activityLogger->record(
            event: ActivityLogEvent::OAuthLoginFailed,
            severity: 'warning',
            metadata: ['reason' => $reason],
            actingAs: ActivityLogActorMode::Guest,
        );
    }
}
