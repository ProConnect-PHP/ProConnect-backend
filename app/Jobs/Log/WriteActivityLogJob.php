<?php

namespace App\Jobs\Log;

use App\Models\Logs\ActivityLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

final class WriteActivityLogJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $backoff = 10;

    public int $timeout = 10;

    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        private readonly array $payload,
    ) {
        $this->onQueue('activity-logs');
    }

    public function handle(): void
    {
        try {
            ActivityLog::query()->create($this->payload);
        } catch (Throwable $exception) {
            Log::warning('Could not write queued activity log to MongoDB.', [
                'event' => $this->payload['event'] ?? null,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    public function failed(Throwable $exception): void
    {
        Log::error('Activity log job failed permanently.', [
            'event' => $this->payload['event'] ?? null,
            'entity_type' => $this->payload['entity_type'] ?? null,
            'entity_id' => $this->payload['entity_id'] ?? null,
            'exception' => $exception::class,
            'message' => $exception->getMessage(),
        ]);
    }
}
