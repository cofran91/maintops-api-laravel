<?php

use App\Http\Controllers\Api\V1\Vehicles\VehicleController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function (): void {
    Route::apiResource('vehicles', VehicleController::class);
});
