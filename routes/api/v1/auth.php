<?php

use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\Auth\ServiceTokenController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->as('auth.')->group(function (): void {
    Route::post('login', [AuthController::class, 'login'])->name('login');
    Route::post('forgot-password', [AuthController::class, 'forgotPassword'])->name('forgot-password');
    Route::post('reset-password', [AuthController::class, 'resetPassword'])->name('reset-password');

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('me', [AuthController::class, 'me'])->name('me');
        Route::patch('language', [AuthController::class, 'updateLanguage'])->name('language.update');
        Route::post('service-token', ServiceTokenController::class)->name('service-token');
        Route::post('logout', [AuthController::class, 'logout'])->name('logout');
    });
});
