<?php

namespace App\Actions\Auth;

use App\Mail\Auth\EmailVerificationMail;
use App\Models\User\EmailVerificationToken;
use App\Models\User\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class SendEmailVerificationAction
{
    /**
     * @return array{already_verified: bool, expires_at: Carbon|null}
     */
    public function __invoke(User $user): array
    {
        if ($user->email_verified_at !== null) {
            return [
                'already_verified' => true,
                'expires_at' => null,
            ];
        }

        $plainToken = bin2hex(random_bytes(32));
        $expiresAt = now()->addMinutes($this->ttlMinutes());

        DB::transaction(function () use ($user, $plainToken, $expiresAt): void {
            EmailVerificationToken::query()
                ->where('user_id', $user->id)
                ->where('email', $user->email)
                ->whereNull('used_at')
                ->update(['used_at' => now()]);

            EmailVerificationToken::query()->create([
                'user_id' => $user->id,
                'email' => $user->email,
                'token_hash' => $this->hashToken($plainToken),
                'expires_at' => $expiresAt,
            ]);
        });

        Mail::to($user->email)->queue(new EmailVerificationMail(
            user: $user,
            verificationUrl: $this->verificationUrl($user->email, $plainToken),
            expiresAt: $expiresAt,
        ));

        return [
            'already_verified' => false,
            'expires_at' => $expiresAt,
        ];
    }

    private function verificationUrl(string $email, string $token): string
    {
        $frontendUrl = rtrim((string) config('proconnect.frontend_url', config('app.url')), '/');

        return $frontendUrl.'/auth/verify-email?'.http_build_query([
            'email' => $email,
            'token' => $token,
        ]);
    }

    private function ttlMinutes(): int
    {
        return max(1, (int) config('proconnect.email_verification.token_ttl_minutes', 60));
    }

    private function hashToken(string $plainToken): string
    {
        return hash('sha256', $plainToken);
    }
}
