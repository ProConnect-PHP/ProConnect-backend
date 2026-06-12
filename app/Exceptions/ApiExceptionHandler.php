<?php

namespace App\Exceptions;

use App\Support\ActivityLog\ActivityLogActorMode;
use App\Support\ActivityLog\ActivityLogEvent;
use App\Support\ActivityLog\ActivityLogger;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

final class ApiExceptionHandler
{
    public static function register(Exceptions $exceptions): void
    {
        $exceptions->shouldRenderJsonWhen(function ($request, Throwable $e) {
            return $request->is('api/*') || $request->expectsJson();
        });

        $exceptions->render(function (ValidationException $e): JsonResponse {
            $errors = $e->errors();

            $flat = [];

            foreach ($errors as $messages) {
                foreach ((array) $messages as $message) {
                    if (is_string($message) && trim($message) !== '') {
                        $flat[] = $message;
                    }
                }
            }

            return ApiExceptionRenderer::render(
                error: 'ValidationError',
                message: $flat[0] ?? 'Error de validación.',
                status: Response::HTTP_UNPROCESSABLE_ENTITY,
                details: $errors
            );
        });

        $exceptions->render(function (AuthenticationException $e): JsonResponse {
            return ApiExceptionRenderer::render(
                error: 'Unauthorized',
                message: 'Token inválido o expirado.',
                status: Response::HTTP_UNAUTHORIZED
            );
        });

        $exceptions->render(function (AuthorizationException $e): JsonResponse {
            app(ActivityLogger::class)->record(
                event: ActivityLogEvent::SecurityForbidden,
                severity: 'warning',
                statusCode: Response::HTTP_FORBIDDEN,
                metadata: [
                    'reason' => $e::class,
                    'route' => request()->route()?->getName(),
                ],
                actingAs: self::requestActorMode(),
            );

            return ApiExceptionRenderer::render(
                error: 'Forbidden',
                message: 'No tienes permisos para realizar esta acción.',
                status: Response::HTTP_FORBIDDEN
            );
        });

        $exceptions->render(function (ThrottleRequestsException $e): JsonResponse {
            app(ActivityLogger::class)->record(
                event: ActivityLogEvent::SecurityRateLimited,
                severity: 'warning',
                statusCode: Response::HTTP_TOO_MANY_REQUESTS,
                metadata: [
                    'path' => request()->path(),
                    'ip' => request()->ip(),
                ],
                actingAs: self::requestActorMode(),
            );

            return ApiExceptionRenderer::render(
                error: 'TooManyRequests',
                message: 'Demasiadas solicitudes. Intenta más tarde.',
                status: Response::HTTP_TOO_MANY_REQUESTS
            );
        });

        $exceptions->render(function (ApiException $e): JsonResponse {
            if ($e->error() === 'BookingSlotAlreadyTaken') {
                $service = request()->route('service');
                $serviceId = is_object($service) && method_exists($service, 'getKey')
                    ? $service->getKey()
                    : $service;

                app(ActivityLogger::class)->record(
                    event: ActivityLogEvent::BookingConflictDetected,
                    entityType: 'service',
                    entityId: $serviceId,
                    severity: 'warning',
                    statusCode: $e->status(),
                    metadata: [
                        'service_id' => $serviceId,
                        'requested_start' => request()->input('starts_at'),
                        'client_id' => request()->user('user_jwt')?->getKey(),
                        'reason' => 'slot_already_taken',
                    ],
                    actingAs: self::requestActorMode(),
                );
            } elseif ($e->status() === Response::HTTP_FORBIDDEN) {
                app(ActivityLogger::class)->record(
                    event: ActivityLogEvent::SecurityForbidden,
                    severity: 'warning',
                    statusCode: $e->status(),
                    metadata: [
                        'reason' => $e->error(),
                        'path' => request()->path(),
                    ],
                    actingAs: self::requestActorMode(),
                );
            }

            return ApiExceptionRenderer::render(
                error: $e->error(),
                message: $e->getMessage(),
                status: $e->status(),
                details: $e->details()
            );
        });

        $exceptions->render(function (ModelNotFoundException $e): JsonResponse {
            return ApiExceptionRenderer::render(
                error: 'NotFound',
                message: 'Recurso no encontrado.',
                status: Response::HTTP_NOT_FOUND
            );
        });

        $exceptions->render(function (HttpExceptionInterface $e): JsonResponse {
            $status = $e->getStatusCode();

            $message = match ($status) {
                Response::HTTP_NOT_FOUND => 'Recurso no encontrado.',
                Response::HTTP_FORBIDDEN => 'No tienes permisos para realizar esta accion.',
                Response::HTTP_METHOD_NOT_ALLOWED => 'Método HTTP no permitido para este endpoint.',
                Response::HTTP_UNSUPPORTED_MEDIA_TYPE => 'Tipo de contenido no soportado.',
                default => 'Ocurrió un error al procesar la solicitud.',
            };

            $error = match ($status) {
                Response::HTTP_NOT_FOUND => 'NotFound',
                Response::HTTP_FORBIDDEN => 'Forbidden',
                Response::HTTP_METHOD_NOT_ALLOWED => 'MethodNotAllowed',
                Response::HTTP_UNSUPPORTED_MEDIA_TYPE => 'UnsupportedMediaType',
                default => 'HttpError',
            };

            return ApiExceptionRenderer::render(
                error: $error,
                message: $message,
                status: $status
            );
        });

        $exceptions->render(function (Throwable $e): JsonResponse {
            Log::error('Unhandled API exception', [
                'exception' => get_class($e),
                'message' => $e->getMessage(),
            ]);

            app(ActivityLogger::class)->record(
                event: ActivityLogEvent::SystemError,
                severity: 'error',
                statusCode: Response::HTTP_INTERNAL_SERVER_ERROR,
                metadata: [
                    'exception' => $e::class,
                    'message' => app()->isProduction()
                        ? 'Internal application error.'
                        : mb_substr($e->getMessage(), 0, 500),
                    'path' => request()->path(),
                    'request_id' => request()->headers->get('X-Request-Id'),
                ],
                actingAs: ActivityLogActorMode::System,
            );

            return ApiExceptionRenderer::render(
                error: 'InternalServerError',
                message: 'Ocurrió un error interno. Intenta nuevamente más tarde.',
                status: Response::HTTP_INTERNAL_SERVER_ERROR
            );
        });
    }

    private static function requestActorMode(): ActivityLogActorMode
    {
        $user = request()->user('user_jwt');

        if (! $user) {
            return ActivityLogActorMode::Guest;
        }

        $booking = request()->route('booking');

        if (
            is_object($booking)
            && isset($booking->client_id)
            && in_array(request()->route()?->getActionMethod(), ['cancel', 'reschedule'], true)
        ) {
            return $booking->client_id === $user->id
                ? ActivityLogActorMode::Client
                : ActivityLogActorMode::Professional;
        }

        $middleware = request()->route()?->gatherMiddleware() ?? [];

        if (in_array('client-capable', $middleware, true)) {
            return ActivityLogActorMode::Client;
        }

        if (in_array('role:professional', $middleware, true)) {
            return ActivityLogActorMode::Professional;
        }

        if (in_array('role:admin', $middleware, true)) {
            return ActivityLogActorMode::Admin;
        }

        return ActivityLogActorMode::fromRole($user->role);
    }
}
