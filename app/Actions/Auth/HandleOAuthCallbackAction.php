<?php

namespace App\Actions\Auth;

use App\Contracts\Auth\IOAuthExchangeCodeStore;
use App\Contracts\Auth\IOAuthIdentityProvider;
use App\DTO\Auth\SocialUserData;
use App\Enums\Auth\OAuthProvider;
use App\Enums\UserRole;
use App\Models\User\User;
use App\Models\User\UserSocialAccount;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class HandleOAuthCallbackAction
{
    public function __construct(
        private readonly IOAuthIdentityProvider $identityProvider,
        private readonly IOAuthExchangeCodeStore $exchangeCodeStore,
    ) {}

    public function execute(string $provider): string
    {
        if (! OAuthProvider::isSupported($provider)) {
            throw new NotFoundHttpException('OAuth provider not supported.');
        }

        $socialUser = $this->identityProvider->userFromCallback($provider);

        $user = $this->findOrCreateUser($socialUser);

        $code = (string) Str::uuid();

        $this->exchangeCodeStore->put(
            code: $code,
            userId: (string) $user->id,
            provider: $provider,
        );

        return $code;
    }

    private function findOrCreateUser(SocialUserData $socialUser): User
    {
        return DB::transaction(function () use ($socialUser): User {
            $socialAccount = UserSocialAccount::query()
                ->where('provider', $socialUser->provider)
                ->where('provider_user_id', $socialUser->providerUserId)
                ->first();

            if ($socialAccount) {
                return $this->synchronizeUser($socialAccount->user, $socialUser);
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

            return $user;
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
