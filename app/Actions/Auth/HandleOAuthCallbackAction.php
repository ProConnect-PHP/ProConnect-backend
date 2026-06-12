<?php

namespace App\Actions\Auth;

use App\Contracts\Auth\IOAuthExchangeCodeStore;
use App\Contracts\Auth\IOAuthIdentityProvider;
use App\DTO\Auth\SocialUserData;
use App\Enums\Auth\OAuthProvider;
use App\Enums\UserRole;
use App\Models\User\User;
use App\Models\User\UserSocialAccount;
use App\Support\ActivityLog\ActivityLogActorMode;
use App\Support\ActivityLog\ActivityLogEvent;
use App\Support\ActivityLog\ActivityLogger;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

final class HandleOAuthCallbackAction
{
    public function __construct(
        private readonly IOAuthIdentityProvider $identityProvider,
        private readonly IOAuthExchangeCodeStore $exchangeCodeStore,
        private readonly ActivityLogger $activityLogger,
    ) {}

    public function execute(string $provider): string
    {
        $socialUser = null;

        try {
            if (! OAuthProvider::isSupported($provider)) {
                throw new NotFoundHttpException('OAuth provider not supported.');
            }

            $socialUser = $this->identityProvider->userFromCallback($provider);

            [$user, $accountLinked] = $this->findOrCreateUser($socialUser);

            $code = (string) Str::uuid();

            $this->exchangeCodeStore->put(
                code: $code,
                userId: (string) $user->id,
                provider: $provider,
            );

            $metadata = [
                'provider' => $socialUser->provider,
                'provider_user_id' => $socialUser->providerUserId,
                'email' => $socialUser->email,
            ];

            if ($accountLinked) {
                $this->activityLogger->record(
                    event: ActivityLogEvent::OAuthAccountLinked,
                    entityType: 'user',
                    entityId: $user->id,
                    entityOwnerId: $user->id,
                    metadata: $metadata,
                    actor: $user,
                    actingAs: ActivityLogActorMode::fromRole($user->role),
                );
            }

            $this->activityLogger->record(
                event: ActivityLogEvent::OAuthLoginSuccess,
                entityType: 'user',
                entityId: $user->id,
                entityOwnerId: $user->id,
                metadata: $metadata,
                actor: $user,
                actingAs: ActivityLogActorMode::fromRole($user->role),
            );

            return $code;
        } catch (Throwable $exception) {
            $this->activityLogger->record(
                event: ActivityLogEvent::OAuthLoginFailed,
                severity: 'warning',
                metadata: [
                    'provider' => $provider,
                    'provider_user_id' => $socialUser?->providerUserId,
                    'email' => $socialUser?->email,
                    'reason' => $exception::class,
                ],
                actingAs: ActivityLogActorMode::Guest,
            );

            throw $exception;
        }
    }

    /**
     * @return array{User, bool}
     */
    private function findOrCreateUser(SocialUserData $socialUser): array
    {
        return DB::transaction(function () use ($socialUser): array {
            $socialAccount = UserSocialAccount::query()
                ->where('provider', $socialUser->provider)
                ->where('provider_user_id', $socialUser->providerUserId)
                ->first();

            if ($socialAccount) {
                return [
                    $this->synchronizeUser($socialAccount->user, $socialUser),
                    false,
                ];
            }

            if ($socialUser->email === null) {
                throw new AuthenticationException('OAuth provider did not return an email address.');
            }

            $user = $this->findExistingUserByEmail($socialUser);

            if (! $user) {
                $user = User::query()->create([
                    'name' => $socialUser->name ?? 'Usuario',
                    'email' => $socialUser->email,
                    'password' => Str::random(64),
                    'role' => UserRole::Client,
                    'avatar_url' => $socialUser->avatarUrl,
                ]);

                $user->forceFill(['email_verified_at' => now()])->save();
            } else {
                $user = $this->synchronizeUser($user, $socialUser);
            }

            UserSocialAccount::query()->create([
                'user_id' => $user->id,
                'provider' => $socialUser->provider,
                'provider_user_id' => $socialUser->providerUserId,
                'email' => $socialUser->email,
                'name' => $socialUser->name,
                'avatar_url' => $socialUser->avatarUrl,
                'linked_at' => now(),
            ]);

            return [$user, true];
        });
    }

    private function synchronizeUser(User $user, SocialUserData $socialUser): User
    {
        if ($user->email_verified_at === null) {
            $user->email_verified_at = now();
        }

        if ($user->avatar_url === null && $socialUser->avatarUrl !== null) {
            $user->avatar_url = $socialUser->avatarUrl;
        }

        if ($user->isDirty()) {
            $user->save();
        }

        return $user;
    }

    private function findExistingUserByEmail(SocialUserData $socialUser): ?User
    {
        if ($socialUser->email === null) {
            return null;
        }

        return User::query()
            ->where('email', $socialUser->email)
            ->first();
    }
}
