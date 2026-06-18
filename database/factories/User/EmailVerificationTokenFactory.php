<?php

namespace Database\Factories\User;

use App\Models\User\EmailVerificationToken;
use App\Models\User\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmailVerificationToken>
 */
class EmailVerificationTokenFactory extends Factory
{
    protected $model = EmailVerificationToken::class;

    public function definition(): array
    {
        $token = bin2hex(random_bytes(32));

        return [
            'user_id' => User::factory()->unverified(),
            'email' => fake()->unique()->safeEmail(),
            'token_hash' => hash('sha256', $token),
            'expires_at' => now()->addHour(),
            'used_at' => null,
        ];
    }

    public function forUser(User $user): static
    {
        return $this->state(fn () => [
            'user_id' => $user->id,
            'email' => $user->email,
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn () => [
            'expires_at' => now()->subMinute(),
        ]);
    }

    public function used(): static
    {
        return $this->state(fn () => [
            'used_at' => now(),
        ]);
    }
}
