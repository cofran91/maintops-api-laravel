<?php

use App\Http\Controllers\Api\V1\Owners\OwnerController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function (): void {
    Route::apiResource('owners', OwnerController::class);
});
