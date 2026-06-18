<?php

namespace Tests\Feature\Auth;

use App\Mail\Auth\EmailVerificationMail;
use App\Models\User\EmailVerificationToken;
use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_unverified_user_can_request_email_verification(): void
    {
        Mail::fake();

        $user = User::factory()->unverified()->create([
            'email' => 'verify-request@example.test',
        ]);

        $this->postJson('/api/v1/auth/email-verification/send', [], $this->authHeaders($user))
            ->assertAccepted()
            ->assertJsonPath('email_verified', false);

        $token = EmailVerificationToken::query()->where('user_id', $user->id)->first();

        $this->assertNotNull($token);
        $this->assertSame($user->email, $token->email);
        $this->assertNotEmpty($token->token_hash);

        Mail::assertQueued(EmailVerificationMail::class, 1);
    }

    public function test_verified_user_does_not_create_unnecessary_token(): void
    {
        Mail::fake();

        $user = User::factory()->create();

        $this->postJson('/api/v1/auth/email-verification/send', [], $this->authHeaders($user))
            ->assertOk()
            ->assertJsonPath('email_verified', true);

        $this->assertDatabaseCount('email_verification_tokens', 0);
        Mail::assertNothingQueued();
    }

    public function test_valid_token_verifies_email(): void
    {
        Mail::fake();

        $user = User::factory()->unverified()->create([
            'email' => 'valid-token@example.test',
        ]);

        $this->postJson('/api/v1/auth/email-verification/send', [], $this->authHeaders($user))
            ->assertAccepted();

        $plainToken = $this->queuedPlainToken();

        $this->postJson('/api/v1/auth/email-verification/verify', [
            'email' => $user->email,
            'token' => $plainToken,
        ])
            ->assertOk()
            ->assertJsonPath('email_verified', true)
            ->assertJsonPath('user.email_verified', true);

        $this->assertNotNull($user->refresh()->email_verified_at);
        $this->assertDatabaseMissing('email_verification_tokens', [
            'user_id' => $user->id,
            'used_at' => null,
        ]);
    }

    public function test_invalid_token_returns_validation_error(): void
    {
        $user = User::factory()->unverified()->create([
            'email' => 'invalid-token@example.test',
        ]);

        EmailVerificationToken::factory()->forUser($user)->create([
            'token_hash' => hash('sha256', 'real-token'),
        ]);

        $this->postJson('/api/v1/auth/email-verification/verify', [
            'email' => $user->email,
            'token' => str_repeat('x', 64),
        ])
            ->assertUnprocessable()
            ->assertJsonPath('error.type', 'InvalidEmailVerificationToken');

        $this->assertNull($user->refresh()->email_verified_at);
    }

    public function test_expired_token_returns_validation_error(): void
    {
        $user = User::factory()->unverified()->create([
            'email' => 'expired-token@example.test',
        ]);
        $plainToken = str_repeat('e', 64);

        EmailVerificationToken::factory()->forUser($user)->expired()->create([
            'token_hash' => hash('sha256', $plainToken),
        ]);

        $this->postJson('/api/v1/auth/email-verification/verify', [
            'email' => $user->email,
            'token' => $plainToken,
        ])
            ->assertUnprocessable()
            ->assertJsonPath('error.type', 'InvalidEmailVerificationToken');
    }

    public function test_verification_is_idempotent_for_already_verified_user(): void
    {
        $user = User::factory()->create([
            'email' => 'already-verified@example.test',
        ]);

        $this->postJson('/api/v1/auth/email-verification/verify', [
            'email' => $user->email,
            'token' => str_repeat('i', 64),
        ])
            ->assertOk()
            ->assertJsonPath('email_verified', true);
    }

    public function test_resend_invalidates_previous_active_tokens(): void
    {
        Mail::fake();

        $user = User::factory()->unverified()->create([
            'email' => 'resend@example.test',
        ]);

        $this->postJson('/api/v1/auth/email-verification/send', [], $this->authHeaders($user))
            ->assertAccepted();

        $firstTokenId = EmailVerificationToken::query()
            ->where('user_id', $user->id)
            ->value('id');

        $this->postJson('/api/v1/auth/email-verification/send', [], $this->authHeaders($user))
            ->assertAccepted();

        $this->assertDatabaseMissing('email_verification_tokens', [
            'id' => $firstTokenId,
            'used_at' => null,
        ]);

        $this->assertSame(1, EmailVerificationToken::query()
            ->where('user_id', $user->id)
            ->whereNull('used_at')
            ->count());
    }

    public function test_resend_is_rate_limited(): void
    {
        Mail::fake();

        $user = User::factory()->unverified()->create([
            'email' => 'rate-limited@example.test',
        ]);

        for ($i = 0; $i < 3; $i++) {
            $this->postJson('/api/v1/auth/email-verification/send', [], $this->authHeaders($user))
                ->assertAccepted();
        }

        $this->postJson('/api/v1/auth/email-verification/send', [], $this->authHeaders($user))
            ->assertTooManyRequests()
            ->assertJsonPath('error.type', 'TooManyRequests');
    }

    public function test_register_dispatches_email_verification_and_exposes_unverified_state(): void
    {
        Mail::fake();

        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Register Verification',
            'email' => 'register-verification@example.test',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('email_verification_required', true)
            ->assertJsonPath('user.email_verified', false)
            ->assertJsonPath('user.email_verified_at', null);

        $this->assertDatabaseHas('email_verification_tokens', [
            'email' => 'register-verification@example.test',
            'used_at' => null,
        ]);

        Mail::assertQueued(EmailVerificationMail::class, 1);
    }

    private function queuedPlainToken(): string
    {
        $plainToken = null;

        Mail::assertQueued(
            EmailVerificationMail::class,
            function (EmailVerificationMail $mail) use (&$plainToken): bool {
                parse_str((string) parse_url($mail->verificationUrl, PHP_URL_QUERY), $query);
                $plainToken = $query['token'] ?? null;

                return is_string($plainToken) && $plainToken !== '';
            }
        );

        $this->assertIsString($plainToken);

        return $plainToken;
    }

    /**
     * @return array<string, string>
     */
    private function authHeaders(User $user): array
    {
        return [
            'Authorization' => 'Bearer '.auth('user_jwt')->login($user),
            'Accept' => 'application/json',
        ];
    }
}
