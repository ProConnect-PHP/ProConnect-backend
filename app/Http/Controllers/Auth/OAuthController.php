<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\ExchangeOAuthCodeAction;
use App\Actions\Auth\HandleOAuthCallbackAction;
use App\Actions\Auth\RedirectToOAuthProviderAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ExchangeOAuthCodeRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

final class OAuthController extends Controller
{
    public function redirect(
        string $provider,
        RedirectToOAuthProviderAction $action,
    ): RedirectResponse {
        return redirect()->away(
            $action->execute($provider)
        );
    }

    public function callback(
        string $provider,
        HandleOAuthCallbackAction $action,
    ): RedirectResponse {
        $code = $action->execute($provider);

        $frontendUrl = rtrim((string) config('app.frontend_url'), '/');

        return redirect()->away(
            "{$frontendUrl}/auth/oauth/callback?code=".rawurlencode($code)
        );
    }

    public function exchange(
        ExchangeOAuthCodeRequest $request,
        ExchangeOAuthCodeAction $action,
    ): JsonResponse {
        return response()->json(
            $action->execute($request->validated('code'))
        );
    }
}
