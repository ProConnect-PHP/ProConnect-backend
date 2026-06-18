<?php

namespace App\Actions\Auth;

use App\Models\User\EmailVerificationToken;
use App\Models\User\User;
use Illuminate\Support\Facades\DB;

class VerifyEmailAction
{
    public function __invoke(string $email, string $plainToken): ?User
    {
        $user = User::query()
            ->where('email', $email)
            ->first();

        if (! $user) {
            return null;
        }

        if ($user->email_verified_at !== null) {
            return $user;
        }

        $tokenHash = $this->hashToken($plainToken);

        /** @var EmailVerificationToken|null $verificationToken */
        $verificationToken = EmailVerificationToken::query()
            ->where('user_id', $user->id)
            ->where('email', $email)
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->orderByDesc('created_at')
            ->get()
            ->first(fn (EmailVerificationToken $token): bool => hash_equals($token->token_hash, $tokenHash));

        if (! $verificationToken) {
            return null;
        }

        DB::transaction(function () use ($user, $email, $verificationToken): void {
            $user->forceFill([
                'email_verified_at' => now(),
            ])->save();

            $verificationToken->forceFill([
                'used_at' => now(),
            ])->save();

            EmailVerificationToken::query()
                ->where('user_id', $user->id)
                ->where('email', $email)
                ->whereNull('used_at')
                ->update(['used_at' => now()]);
        });

        return $user->refresh();
    }

    private function hashToken(string $plainToken): string
    {
        return hash('sha256', $plainToken);
    }
}
