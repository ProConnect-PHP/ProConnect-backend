<?php

use App\Exceptions\ApiExceptionHandler;
use App\Http\Middleware\EnsureUserCanActAsClient;
use App\Http\Middleware\EnsureUserHasRole;
use App\Http\Middleware\ForceJsonResponse;
use App\Http\Middleware\LogRequestPerformance;
use App\Providers\BroadcastServiceProvider;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withCommands([
        __DIR__.'/../app/Console/Commands',
    ])
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'client-capable' => EnsureUserCanActAsClient::class,
            'role' => EnsureUserHasRole::class,
        ]);

        $middleware->api(prepend: [
            ForceJsonResponse::class,
            LogRequestPerformance::class,
        ]);
    })
    ->withProviders([
        BroadcastServiceProvider::class,
    ])
    ->withExceptions(function (Exceptions $exceptions): void {
        ApiExceptionHandler::register($exceptions);
    })->create();
