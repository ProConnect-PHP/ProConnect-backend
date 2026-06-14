<?php

namespace Tests\Feature\ActivityLog;

use App\Models\User\User;
use App\Support\ActivityLog\ActivityLogEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthActivityLogTest extends TestCase
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

    public function test_successful_login_creates_activity_log(): void
    {
        $user = User::factory()->create([
            'email' => 'activity-login@example.test',
            'password' => 'password',
        ]);

        $this
            ->withServerVariables(['REMOTE_ADDR' => '198.51.100.71'])
            ->postJson('/api/v1/auth/login', [
                'email' => $user->email,
                'password' => 'password',
            ])
            ->assertOk();

        $log = $this->activityLog(ActivityLogEvent::AuthLoginSuccess->value);

        $this->assertNotNull($log);
        $this->assertSame($user->id, $log->actor_id);
        $this->assertSame('password', $log->metadata['login_method']);
    }

    public function test_failed_login_creates_warning_activity_log(): void
    {
        User::factory()->create([
            'email' => 'activity-failed@example.test',
            'password' => 'password',
        ]);

        $this
            ->withServerVariables(['REMOTE_ADDR' => '198.51.100.72'])
            ->postJson('/api/v1/auth/login', [
                'email' => 'activity-failed@example.test',
                'password' => 'invalid-password',
            ])
            ->assertUnauthorized();

        $log = $this->activityLog(ActivityLogEvent::AuthLoginFailed->value);

        $this->assertNotNull($log);
        $this->assertSame('warning', $log->severity);
        $this->assertSame('invalid_credentials', $log->metadata['reason']);
        $this->assertArrayNotHasKey('password', $log->metadata);
    }
}
