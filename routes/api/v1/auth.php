<?php

use App\Http\Controllers\Api\V1\Auth\LoginController;
use App\Http\Controllers\Api\V1\Auth\LogoutController;
use App\Http\Controllers\Api\V1\Auth\MeController;
use App\Http\Controllers\Api\V1\Auth\ServiceTokenController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->as('auth.')->group(function (): void {
    Route::post('login', LoginController::class)->name('login');

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('me', MeController::class)->name('me');
        Route::post('service-token', ServiceTokenController::class)->name('service-token');
        Route::post('logout', LogoutController::class)->name('logout');
    });
});
