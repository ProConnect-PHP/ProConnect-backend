<?php

use App\Exceptions\ApiExceptionHandler;
use App\Http\Middleware\EnsureEmailIsVerified;
use App\Http\Middleware\EnsureJwtWasIssuedAfterPasswordChange;
use App\Http\Middleware\EnsureUserCanActAsClient;
use App\Http\Middleware\EnsureUserIsAdmin;
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
            'admin' => EnsureUserIsAdmin::class,
            'client-capable' => EnsureUserCanActAsClient::class,
            'role' => EnsureUserHasRole::class,
            'verified.email' => EnsureEmailIsVerified::class,
            'jwt.password.fresh' => EnsureJwtWasIssuedAfterPasswordChange::class,
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
