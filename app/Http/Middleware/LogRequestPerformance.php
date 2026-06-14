<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

final class LogRequestPerformance
{
    public function handle(Request $request, Closure $next): Response
    {
        $startedAt = microtime(true);

        $queryCount = 0;
        $queryTimeMs = 0.0;

        DB::listen(function (QueryExecuted $query) use (&$queryCount, &$queryTimeMs): void {
            $queryCount++;
            $queryTimeMs += $query->time;
        });

        /** @var Response $response */
        $response = $next($request);

        $durationMs = round((microtime(true) - $startedAt) * 1000, 2);

        Log::warning('Request performance', [
            'method' => $request->method(),
            'path' => $request->path(),
            'status' => $response->getStatusCode(),
            'duration_ms' => $durationMs,
            'query_count' => $queryCount,
            'query_time_ms' => round($queryTimeMs, 2),
            'php_time_ms' => round($durationMs - $queryTimeMs, 2),
            'memory_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
        ]);

        return $response;
    }
}
