<?php

namespace Tests\Feature\Auth;

use App\Contracts\Auth\IOAuthExchangeCodeStore;
use App\Contracts\Auth\IOAuthIdentityProvider;
use App\DTO\Auth\SocialUserData;
use App\Models\User\User;
use App\Models\User\UserSocialAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Tests\TestCase;

class OAuthApiTest extends TestCase
{
    use RefreshDatabase;

    private static int $requestIpSuffix = 10;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set([
            'app.frontend_url' => 'http://localhost:4200',
            'services.google.client_id' => 'google-client-id.test',
            'services.google.client_secret' => 'google-client-secret.test',
            'services.google.redirect' => 'http://localhost/api/v1/auth/oauth/google/callback',
        ]);

        $this->withServerVariables([
            'REMOTE_ADDR' => '198.51.100.'.self::$requestIpSuffix++,
        ]);
    }

    public function test_oauth_redirect_rejects_unsupported_provider(): void
    {
        $this->getJson('/api/v1/auth/oauth/facebook/redirect')
            ->assertNotFound()
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.type', 'NotFound');
    }

    public function test_oauth_redirect_generates_google_redirect_url(): void
    {
        $response = $this->get('/api/v1/auth/oauth/google/redirect');

        $response->assertRedirect();

        $query = $this->redirectQuery($response->headers->get('Location'));

        $this->assertSame('google-client-id.test', $query['client_id']);
        $this->assertSame(
            'http://localhost/api/v1/auth/oauth/google/callback',
            $query['redirect_uri']
        );
        $this->assertSame('code', $query['response_type']);
        $this->assertSame(
            ['openid', 'profile', 'email'],
            explode(' ', $query['scope'])
        );
    }

    public function test_oauth_exchange_requires_code(): void
    {
        $this->postJson('/api/v1/auth/oauth/exchange')
            ->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.type', 'ValidationError')
            ->assertJsonStructure([
                'error' => [
                    'details' => ['code'],
                ],
            ]);
    }

    public function test_oauth_exchange_rejects_invalid_code(): void
    {
        $this->postJson('/api/v1/auth/oauth/exchange', [
            'code' => (string) Str::uuid(),
        ])
            ->assertUnauthorized()
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.type', 'Unauthorized');
    }

    public function test_oauth_exchange_code_can_only_be_used_once(): void
    {
        $user = User::factory()->create();
        $code = $this->storeExchangeCode($user);

        $this->assertTrue(Cache::has("oauth_exchange:{$code}"));

        $this->postJson('/api/v1/auth/oauth/exchange', ['code' => $code])
            ->assertOk();

        $this->assertFalse(Cache::has("oauth_exchange:{$code}"));

        $this->postJson('/api/v1/auth/oauth/exchange', ['code' => $code])
            ->assertUnauthorized()
            ->assertJsonPath('error.type', 'Unauthorized');
    }

    public function test_oauth_exchange_returns_same_auth_response_shape_as_login(): void
    {
        $user = User::factory()->create([
            'email' => 'same-contract@example.test',
            'password' => 'password',
        ]);

        $login = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertOk();

        $oauth = $this->postJson('/api/v1/auth/oauth/exchange', [
            'code' => $this->storeExchangeCode($user),
        ])->assertOk();

        $expectedKeys = [
            'access_token',
            'refresh_token',
            'token_type',
            'expires_in',
            'user',
        ];

        $this->assertEqualsCanonicalizing($expectedKeys, array_keys($login->json()));
        $this->assertEqualsCanonicalizing($expectedKeys, array_keys($oauth->json()));
        $this->assertSame($login->json('user'), $oauth->json('user'));
        $this->assertSame('bearer', $oauth->json('token_type'));
        $this->assertDatabaseCount('refresh_tokens', 2);
    }

    public function test_oauth_callback_creates_user_for_new_google_account(): void
    {
        $this->fakeSocialUser(new SocialUserData(
            provider: 'google',
            providerUserId: 'google-new-user',
            email: 'new-google-user@example.test',
            name: 'New Google User',
            avatarUrl: 'https://example.test/avatar.png',
        ));

        $callback = $this->get('/api/v1/auth/oauth/google/callback')
            ->assertRedirect();

        $code = $this->exchangeCodeFromCallback($callback->headers->get('Location'));

        $this->assertDatabaseHas('users', [
            'email' => 'new-google-user@example.test',
            'name' => 'New Google User',
            'role' => 'client',
            'avatar_url' => 'https://example.test/avatar.png',
        ]);
        $this->assertDatabaseHas('user_social_accounts', [
            'provider' => 'google',
            'provider_user_id' => 'google-new-user',
            'email' => 'new-google-user@example.test',
        ]);

        $this->assertNotNull(
            User::query()
                ->where('email', 'new-google-user@example.test')
                ->value('email_verified_at')
        );

        $this->postJson('/api/v1/auth/oauth/exchange', ['code' => $code])
            ->assertOk()
            ->assertJsonPath('user.role', 'client')
            ->assertJsonPath('user.avatar_url', 'https://example.test/avatar.png')
            ->assertJsonStructure([
                'access_token',
                'refresh_token',
                'token_type',
                'expires_in',
                'user' => ['id', 'name', 'email', 'role', 'avatar_url'],
            ]);
    }

    public function test_oauth_callback_links_social_account_to_existing_user_by_email(): void
    {
        $user = User::factory()->create([
            'email' => 'existing@example.test',
            'email_verified_at' => null,
            'avatar_url' => null,
        ]);

        $this->fakeSocialUser(new SocialUserData(
            provider: 'google',
            providerUserId: 'google-existing-user',
            email: $user->email,
            name: 'Existing User',
            avatarUrl: 'https://example.test/existing-avatar.png',
        ));

        $this->get('/api/v1/auth/oauth/google/callback')
            ->assertRedirect();

        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseHas('user_social_accounts', [
            'user_id' => $user->id,
            'provider' => 'google',
            'provider_user_id' => 'google-existing-user',
        ]);
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'avatar_url' => 'https://example.test/existing-avatar.png',
        ]);
        $this->assertNotNull($user->refresh()->email_verified_at);
    }

    public function test_oauth_callback_reuses_existing_social_account_without_duplicates(): void
    {
        $user = User::factory()->create([
            'email' => 'linked@example.test',
            'email_verified_at' => null,
        ]);

        UserSocialAccount::query()->create([
            'user_id' => $user->id,
            'provider' => 'google',
            'provider_user_id' => 'google-linked-user',
            'email' => $user->email,
            'name' => $user->name,
            'linked_at' => now(),
        ]);

        $this->fakeSocialUser(new SocialUserData(
            provider: 'google',
            providerUserId: 'google-linked-user',
            email: $user->email,
            name: $user->name,
            avatarUrl: null,
        ));

        $callback = $this->get('/api/v1/auth/oauth/google/callback')
            ->assertRedirect();

        $code = $this->exchangeCodeFromCallback($callback->headers->get('Location'));

        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('user_social_accounts', 1);
        $this->assertNotNull($user->refresh()->email_verified_at);

        $this->postJson('/api/v1/auth/oauth/exchange', ['code' => $code])
            ->assertOk()
            ->assertJsonPath('user.id', $user->id);
    }

    public function test_oauth_exchange_rejects_expired_code(): void
    {
        $user = User::factory()->create();
        $code = $this->storeExchangeCode($user);

        $this->travel(3)->minutes();

        $this->postJson('/api/v1/auth/oauth/exchange', ['code' => $code])
            ->assertUnauthorized()
            ->assertJsonPath('error.type', 'Unauthorized');
    }

    public function test_oauth_exchange_rejects_expired_payload_even_if_cache_entry_remains(): void
    {
        $user = User::factory()->create();
        $code = (string) Str::uuid();

        Cache::put("oauth_exchange:{$code}", [
            'user_id' => (string) $user->id,
            'provider' => 'google',
            'expires_at' => now()->subSecond()->getTimestamp(),
        ], 600);

        $this->assertTrue(Cache::has("oauth_exchange:{$code}"));

        $this->postJson('/api/v1/auth/oauth/exchange', ['code' => $code])
            ->assertUnauthorized()
            ->assertJsonPath('error.type', 'Unauthorized');

        $this->assertFalse(Cache::has("oauth_exchange:{$code}"));
    }

    private function fakeSocialUser(SocialUserData $socialUser): void
    {
        $this->app->instance(
            IOAuthIdentityProvider::class,
            new class($socialUser) implements IOAuthIdentityProvider
            {
                public function __construct(
                    private readonly SocialUserData $socialUser,
                ) {}

                public function redirectUrl(string $provider): string
                {
                    return 'https://accounts.example.test/oauth';
                }

                public function userFromCallback(string $provider): SocialUserData
                {
                    return $this->socialUser;
                }
            }
        );
    }

    private function storeExchangeCode(User $user): string
    {
        $code = (string) Str::uuid();

        app(IOAuthExchangeCodeStore::class)->put(
            code: $code,
            userId: (string) $user->id,
            provider: 'google',
        );

        return $code;
    }

    /**
     * @return array<string, string>
     */
    private function redirectQuery(?string $location): array
    {
        $this->assertNotNull($location);

        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);

        return $query;
    }

    private function exchangeCodeFromCallback(?string $location): string
    {
        $this->assertNotNull($location);
        $this->assertStringStartsWith(
            'http://localhost:4200/auth/oauth/callback?',
            $location
        );

        $query = $this->redirectQuery($location);
        $code = $query['code'] ?? null;

        $this->assertIsString($code);
        $this->assertTrue(Str::isUuid($code));
        $this->assertTrue(Cache::has("oauth_exchange:{$code}"));

        return $code;
    }
}
