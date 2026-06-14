<?php

namespace Tests\Feature\Notification;

use App\Models\Notification\Notification;
use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class NotificationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_notification_routes_require_authentication(): void
    {
        $this->getJson('/api/v1/notifications')
            ->assertUnauthorized()
            ->assertJsonPath('error.type', 'Unauthorized');
    }

    public function test_user_can_list_only_their_notifications(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $older = $this->createNotification($user, [
            'title' => 'Older notification',
            'metadata' => ['booking_id' => 'booking-1'],
        ], now()->subMinute());
        $newer = $this->createNotification($user, [
            'title' => 'Newer notification',
        ], now());
        $this->createNotification($otherUser, [
            'title' => 'Foreign notification',
        ], now()->addMinute());

        $response = $this
            ->withHeaders($this->authHeaders($user))
            ->getJson('/api/v1/notifications');

        $response
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $newer->id)
            ->assertJsonPath('data.1.id', $older->id)
            ->assertJsonPath('data.1.metadata.booking_id', 'booking-1')
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'type',
                        'title',
                        'message',
                        'action_route',
                        'metadata',
                        'is_read',
                        'is_archived',
                        'read_at',
                        'archived_at',
                        'created_at',
                        'created_date',
                        'created_time',
                    ],
                ],
                'links',
                'meta',
            ]);
    }

    public function test_user_can_list_active_notifications_by_default(): void
    {
        $user = User::factory()->create();
        $visible = $this->createNotification($user);
        $this->createNotification($user, [
            'archived_at' => now(),
        ]);

        $this
            ->withHeaders($this->authHeaders($user))
            ->getJson('/api/v1/notifications')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $visible->id);

        $this
            ->withHeaders($this->authHeaders($user))
            ->getJson('/api/v1/notifications?status=active')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $visible->id);
    }

    public function test_user_can_list_archived_notifications(): void
    {
        $user = User::factory()->create();
        $this->createNotification($user);
        $archived = $this->createNotification($user, [
            'archived_at' => now(),
        ]);

        $this
            ->withHeaders($this->authHeaders($user))
            ->getJson('/api/v1/notifications?status=archived')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $archived->id)
            ->assertJsonPath('data.0.is_archived', true);
    }

    public function test_user_can_list_all_notifications(): void
    {
        $user = User::factory()->create();
        $this->createNotification($user);
        $this->createNotification($user, [
            'archived_at' => now(),
        ]);

        $this
            ->withHeaders($this->authHeaders($user))
            ->getJson('/api/v1/notifications?status=all')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_user_can_include_archived_notifications(): void
    {
        $user = User::factory()->create();
        $this->createNotification($user);
        $this->createNotification($user, [
            'archived_at' => now(),
        ]);

        $this
            ->withHeaders($this->authHeaders($user))
            ->getJson('/api/v1/notifications?include_archived=true')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_invalid_notification_status_returns_422(): void
    {
        $user = User::factory()->create();

        $this
            ->withHeaders($this->authHeaders($user))
            ->getJson('/api/v1/notifications?status=deleted')
            ->assertUnprocessable()
            ->assertJsonPath('error.type', 'ValidationError')
            ->assertJsonStructure([
                'error' => [
                    'details' => ['status'],
                ],
            ]);
    }

    public function test_per_page_cannot_exceed_one_hundred(): void
    {
        $user = User::factory()->create();

        $this
            ->withHeaders($this->authHeaders($user))
            ->getJson('/api/v1/notifications?per_page=101')
            ->assertUnprocessable()
            ->assertJsonPath('error.type', 'ValidationError')
            ->assertJsonStructure([
                'error' => [
                    'details' => ['per_page'],
                ],
            ]);
    }

    public function test_unread_count_ignores_read_and_archived_notifications(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $this->createNotification($user);
        $this->createNotification($user, [
            'read_at' => now(),
        ]);
        $this->createNotification($user, [
            'archived_at' => now(),
        ]);
        $this->createNotification($otherUser);

        $this
            ->withHeaders($this->authHeaders($user))
            ->getJson('/api/v1/notifications/unread-count')
            ->assertOk()
            ->assertJsonPath('count', 1);
    }

    public function test_user_can_mark_their_notification_as_read(): void
    {
        $user = User::factory()->create();
        $notification = $this->createNotification($user);

        $this
            ->withHeaders($this->authHeaders($user))
            ->patchJson("/api/v1/notifications/{$notification->id}/read")
            ->assertOk()
            ->assertJsonPath('data.id', $notification->id)
            ->assertJsonPath('data.is_read', true);

        $this->assertNotNull($notification->refresh()->read_at);
    }

    public function test_mark_as_read_is_idempotent(): void
    {
        $user = User::factory()->create();
        $readAt = now()->subHour()->startOfSecond();
        $notification = $this->createNotification($user, [
            'read_at' => $readAt,
        ]);

        $this
            ->withHeaders($this->authHeaders($user))
            ->patchJson("/api/v1/notifications/{$notification->id}/read")
            ->assertOk()
            ->assertJsonPath('data.id', $notification->id)
            ->assertJsonPath('data.is_read', true);

        $this->assertTrue(
            $notification->refresh()->read_at->equalTo($readAt)
        );
    }

    public function test_user_cannot_mark_another_users_notification_as_read(): void
    {
        $user = User::factory()->create();
        $notification = $this->createNotification(
            User::factory()->create()
        );

        $this
            ->withHeaders($this->authHeaders($user))
            ->patchJson("/api/v1/notifications/{$notification->id}/read")
            ->assertForbidden()
            ->assertJsonPath('error.type', 'Forbidden');

        $this->assertNull($notification->refresh()->read_at);
    }

    public function test_user_can_mark_all_visible_notifications_as_read(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $first = $this->createNotification($user);
        $second = $this->createNotification($user);
        $archived = $this->createNotification($user, [
            'archived_at' => now(),
        ]);
        $foreign = $this->createNotification($otherUser);

        $this
            ->withHeaders($this->authHeaders($user))
            ->patchJson('/api/v1/notifications/read-all')
            ->assertOk()
            ->assertJsonPath('message', 'All notifications marked as read')
            ->assertJsonPath('updated', 2);

        $this->assertNotNull($first->refresh()->read_at);
        $this->assertNotNull($second->refresh()->read_at);
        $this->assertNull($archived->refresh()->read_at);
        $this->assertNull($foreign->refresh()->read_at);
    }

    public function test_user_can_archive_their_notification(): void
    {
        $user = User::factory()->create();
        $notification = $this->createNotification($user);

        $this
            ->withHeaders($this->authHeaders($user))
            ->patchJson("/api/v1/notifications/{$notification->id}/archive")
            ->assertOk()
            ->assertJsonPath('data.id', $notification->id)
            ->assertJsonPath('data.is_archived', true);

        $this->assertNotNull($notification->refresh()->archived_at);
    }

    public function test_archive_is_idempotent(): void
    {
        $user = User::factory()->create();
        $archivedAt = now()->subHour()->startOfSecond();
        $notification = $this->createNotification($user, [
            'archived_at' => $archivedAt,
        ]);

        $this
            ->withHeaders($this->authHeaders($user))
            ->patchJson("/api/v1/notifications/{$notification->id}/archive")
            ->assertOk()
            ->assertJsonPath('data.id', $notification->id)
            ->assertJsonPath('data.is_archived', true);

        $this->assertTrue(
            $notification->refresh()->archived_at->equalTo($archivedAt)
        );
    }

    public function test_user_cannot_archive_another_users_notification(): void
    {
        $user = User::factory()->create();
        $notification = $this->createNotification(
            User::factory()->create()
        );

        $this
            ->withHeaders($this->authHeaders($user))
            ->patchJson("/api/v1/notifications/{$notification->id}/archive")
            ->assertForbidden()
            ->assertJsonPath('error.type', 'Forbidden');

        $this->assertNull($notification->refresh()->archived_at);
    }

    public function test_user_can_unarchive_their_notification(): void
    {
        $user = User::factory()->create();
        $notification = $this->createNotification($user, [
            'archived_at' => now(),
        ]);

        $this
            ->withHeaders($this->authHeaders($user))
            ->patchJson("/api/v1/notifications/{$notification->id}/unarchive")
            ->assertOk()
            ->assertJsonPath('data.id', $notification->id)
            ->assertJsonPath('data.is_archived', false)
            ->assertJsonPath('data.archived_at', null);

        $this->assertNull($notification->refresh()->archived_at);
    }

    public function test_unarchive_is_idempotent(): void
    {
        $user = User::factory()->create();
        $notification = $this->createNotification($user);

        $this
            ->withHeaders($this->authHeaders($user))
            ->patchJson("/api/v1/notifications/{$notification->id}/unarchive")
            ->assertOk()
            ->assertJsonPath('data.id', $notification->id)
            ->assertJsonPath('data.is_archived', false)
            ->assertJsonPath('data.archived_at', null);

        $this->assertNull($notification->refresh()->archived_at);
    }

    public function test_user_cannot_unarchive_another_users_notification(): void
    {
        $user = User::factory()->create();
        $archivedAt = now()->subHour()->startOfSecond();
        $notification = $this->createNotification(
            User::factory()->create(),
            ['archived_at' => $archivedAt]
        );

        $this
            ->withHeaders($this->authHeaders($user))
            ->patchJson("/api/v1/notifications/{$notification->id}/unarchive")
            ->assertForbidden()
            ->assertJsonPath('error.type', 'Forbidden');

        $this->assertTrue(
            $notification->refresh()->archived_at->equalTo($archivedAt)
        );
    }

    public function test_user_can_physically_delete_their_notification(): void
    {
        $user = User::factory()->create();
        $notification = $this->createNotification($user);

        $this
            ->withHeaders($this->authHeaders($user))
            ->deleteJson("/api/v1/notifications/{$notification->id}")
            ->assertOk()
            ->assertJsonPath('message', 'Notification deleted');

        $this->assertDatabaseMissing('notifications', [
            'id' => $notification->id,
        ]);
    }

    public function test_user_cannot_delete_another_users_notification(): void
    {
        $user = User::factory()->create();
        $notification = $this->createNotification(
            User::factory()->create()
        );

        $this
            ->withHeaders($this->authHeaders($user))
            ->deleteJson("/api/v1/notifications/{$notification->id}")
            ->assertForbidden()
            ->assertJsonPath('error.type', 'Forbidden');

        $this->assertDatabaseHas('notifications', [
            'id' => $notification->id,
        ]);
    }

    private function authHeaders(User $user): array
    {
        $token = auth('user_jwt')->login($user);

        return [
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ];
    }

    private function createNotification(
        User $recipient,
        array $overrides = [],
        ?Carbon $createdAt = null
    ): Notification {
        $notification = Notification::query()->create(array_merge([
            'recipient_id' => $recipient->id,
            'type' => 'booking.updated',
            'title' => 'Booking updated',
            'message' => 'Your booking was updated.',
            'action_route' => '/bookings',
            'metadata' => [],
            'read_at' => null,
            'archived_at' => null,
        ], $overrides));

        if ($createdAt !== null) {
            $notification->forceFill([
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ])->save();
        }

        return $notification->refresh();
    }
}
