<?php

namespace Database\Factories\Video;

use App\Models\User\User;
use App\Models\Video\VideoSession;
use App\Models\Video\VideoSessionParticipant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VideoSessionParticipant>
 */
class VideoSessionParticipantFactory extends Factory
{
    protected $model = VideoSessionParticipant::class;

    public function definition(): array
    {
        $role = fake()->randomElement(['client', 'professional']);
        $user = User::factory()->create();

        return [
            'video_session_id' => VideoSession::factory(),
            'user_id' => $user->id,
            'role' => $role,
            'provider_identity' => $role.'-'.$user->id,
            'display_name' => $user->name,
            'first_joined_at' => now(),
            'last_joined_at' => now(),
            'left_at' => null,
            'join_count' => 1,
            'metadata' => [
                'provider' => 'simulator',
            ],
        ];
    }

    public function forVideoSession(VideoSession $videoSession): static
    {
        return $this->state(fn () => [
            'video_session_id' => $videoSession->id,
        ]);
    }

    public function forUser(User $user, string $role): static
    {
        return $this->state(fn () => [
            'user_id' => $user->id,
            'role' => $role,
            'provider_identity' => $role.'-'.$user->id,
            'display_name' => $user->name,
        ]);
    }
}
