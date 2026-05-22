<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\User\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {

    Route::prefix('auth')->group(function () {
        Route::post('/login', [AuthController::class, 'login']);
        Route::post('/refresh', [AuthController::class, 'refresh']);
        Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:user_jwt');
    });

    Route::apiResource('users', UserController::class);
    Route::middleware('auth:user_jwt')->group(function () {
        Route::get('/users/me', [UserController::class, 'show']);
        Route::put('/users/me', [UserController::class, 'update']);
    });
});
