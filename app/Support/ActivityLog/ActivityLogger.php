<?php

namespace App\Support\ActivityLog;

use App\Models\Logs\ActivityLog;
use App\Models\User\User;
use BackedEnum;
use DateTimeInterface;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Stringable;
use Throwable;

final class ActivityLogger
{
    private const SENSITIVE_KEYS = [
        'password',
        'password_confirmation',
        'token',
        'access_token',
        'refresh_token',
        'authorization',
        'card_number',
        'cvv',
        'secret',
        'client_secret',
        'provider_token',
    ];

    public function record(
        string|ActivityLogEvent $event,
        ?string $entityType = null,
        string|int|null $entityId = null,
        array $metadata = [],
        string $severity = 'info',
        string|int|null $entityOwnerId = null,
        ?int $statusCode = null,
        ?Request $request = null,
        ?User $actor = null,
        string|ActivityLogActorMode|null $actingAs = null,
    ): void {
        $eventName = $event instanceof ActivityLogEvent ? $event->value : $event;

        try {
            $request ??= app()->bound('request') ? request() : null;
            $actor ??= $request?->user('user_jwt');
            $actorRole = $actor?->role;

            ActivityLog::query()->create([
                'event' => $eventName,
                'severity' => $severity,
                'actor_id' => $actor?->getKey(),
                'actor_email' => $actor?->email,
                'actor_role' => $actorRole instanceof BackedEnum ? $actorRole->value : $actorRole,
                'actor_type' => $actor ? 'user' : 'guest',
                'acting_as' => $actingAs instanceof ActivityLogActorMode
                    ? $actingAs->value
                    : $actingAs,
                'entity_type' => $entityType,
                'entity_id' => $entityId === null ? null : (string) $entityId,
                'entity_owner_id' => $entityOwnerId === null ? null : (string) $entityOwnerId,
                'ip' => $request?->ip(),
                'user_agent' => $request?->userAgent(),
                'request_id' => $request?->headers->get('X-Request-Id'),
                'method' => $request?->method(),
                'path' => $request?->path(),
                'status_code' => $statusCode,
                'metadata' => $this->sanitizeArray($metadata),
                'created_at' => now(),
            ]);
        } catch (Throwable $exception) {
            Log::warning('Could not write activity log to MongoDB.', [
                'event' => $eventName,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    private function sanitizeArray(array $data): array
    {
        foreach ($data as $key => $value) {
            if (in_array(strtolower((string) $key), self::SENSITIVE_KEYS, true)) {
                $data[$key] = '[redacted]';

                continue;
            }

            $data[$key] = $this->normalizeValue($value);
        }

        return $data;
    }

    private function normalizeValue(mixed $value): mixed
    {
        return match (true) {
            is_array($value) => $this->sanitizeArray($value),
            $value instanceof BackedEnum => $value->value,
            $value instanceof DateTimeInterface => $value->format(DATE_ATOM),
            $value instanceof Arrayable => $this->sanitizeArray($value->toArray()),
            $value instanceof Stringable => (string) $value,
            is_object($value) => $value::class,
            default => $value,
        };
    }
}
