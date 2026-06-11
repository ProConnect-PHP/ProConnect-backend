<?php

namespace App\Actions\Auth;

use App\Contracts\Auth\IOAuthIdentityProvider;
use App\Enums\Auth\OAuthProvider;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class RedirectToOAuthProviderAction
{
    public function __construct(
        private readonly IOAuthIdentityProvider $identityProvider,
    ) {
    }

    public function execute(string $provider): string
    {
        if (! OAuthProvider::isSupported($provider)) {
            throw new NotFoundHttpException('OAuth provider not supported.');
        }

        return $this->identityProvider->redirectUrl($provider);
    }
}
