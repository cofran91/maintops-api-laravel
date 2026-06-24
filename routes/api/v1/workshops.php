<?php

use App\Http\Controllers\Api\V1\Workshops\WorkshopController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function (): void {
    Route::apiResource('workshops', WorkshopController::class);
});
