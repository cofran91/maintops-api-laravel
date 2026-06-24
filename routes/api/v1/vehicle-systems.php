<?php

use App\Http\Controllers\Api\V1\VehicleSystems\VehicleSystemController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function (): void {
    Route::apiResource('vehicle-systems', VehicleSystemController::class)->only(['index']);
});
