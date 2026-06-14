<?php

namespace Tests\Feature\ActivityLog;

use App\Models\Logs\ActivityLog;
use App\Models\User\User;
use App\Support\ActivityLog\ActivityLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;

class ActivityLoggerTest extends TestCase
{
    use InteractsWithActivityLogs;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->useSynchronousActivityLogQueue();
        $this->clearActivityLogs();
    }

    protected function tearDown(): void
    {
        $this->clearActivityLogs();

        parent::tearDown();
    }

    public function test_activity_logger_writes_document_to_mongodb(): void
    {
        $user = User::factory()->create();

        app(ActivityLogger::class)->record(
            event: 'system.test',
            entityType: 'user',
            entityId: $user->id,
            metadata: ['source' => 'feature-test'],
            actor: $user,
        );

        $log = $this->activityLog('system.test');

        $this->assertNotNull($log);
        $this->assertSame($user->id, $log->actor_id);
        $this->assertSame('feature-test', $log->metadata['source']);
    }

    public function test_sensitive_metadata_is_redacted(): void
    {
        app(ActivityLogger::class)->record(
            event: 'system.sensitive-test',
            metadata: [
                'password' => 'plain-text',
                'nested' => [
                    'access_token' => 'access-secret',
                    'safe' => 'visible',
                ],
            ],
        );

        $metadata = $this->activityLog('system.sensitive-test')?->metadata;

        $this->assertSame('[redacted]', $metadata['password']);
        $this->assertSame('[redacted]', $metadata['nested']['access_token']);
        $this->assertSame('visible', $metadata['nested']['safe']);
    }

    public function test_activity_logger_does_not_break_when_mongodb_fails(): void
    {
        $original = config('database.connections.mongodb');
        $originalLogger = Log::getFacadeRoot();

        try {
            config()->set('database.connections.mongodb', [
                ...$original,
                'dsn' => 'mongodb://127.0.0.1:1/proconnect_logs',
                'options' => [
                    'serverSelectionTimeoutMS' => 50,
                    'connectTimeoutMS' => 50,
                ],
            ]);

            DB::purge('mongodb');

            Log::spy();

            app(ActivityLogger::class)->record(event: 'system.mongodb-unavailable');

            Log::shouldHaveReceived('warning')
                ->with(
                    'Could not write queued activity log to MongoDB.',
                    Mockery::on(
                        fn (array $context): bool => ($context['event'] ?? null) === 'system.mongodb-unavailable'
                    )
                )
                ->once();

            Log::shouldHaveReceived('warning')
                ->with(
                    'Could not dispatch activity log job.',
                    Mockery::on(
                        fn (array $context): bool => ($context['event'] ?? null) === 'system.mongodb-unavailable'
                    )
                )
                ->once();
        } finally {
            config()->set('database.connections.mongodb', $original);
            DB::purge('mongodb');

            Log::swap($originalLogger);
        }
    }

    public function test_admin_can_filter_activity_logs(): void
    {
        $admin = User::factory()->admin()->create();

        ActivityLog::query()->create([
            'event' => 'booking.created',
            'severity' => 'info',
            'entity_type' => 'booking',
            'entity_id' => 'booking-visible',
            'metadata' => [],
            'created_at' => now(),
        ]);
        ActivityLog::query()->create([
            'event' => 'payment.created',
            'severity' => 'info',
            'entity_type' => 'payment_intent',
            'entity_id' => 'payment-hidden',
            'metadata' => [],
            'created_at' => now(),
        ]);

        $response = $this
            ->withHeaders($this->authHeaders($admin))
            ->getJson('/api/v1/admin/activity-logs?event=booking.created&limit=10');

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.event', 'booking.created')
            ->assertJsonPath('data.0.entity_id', 'booking-visible');
    }

    private function authHeaders(User $user): array
    {
        return [
            'Authorization' => 'Bearer '.auth('user_jwt')->login($user),
            'Accept' => 'application/json',
        ];
    }
}
