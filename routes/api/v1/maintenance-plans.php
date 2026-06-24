<?php

use App\Http\Controllers\Api\V1\MaintenancePlans\MaintenancePlanController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function (): void {
    Route::apiResource('maintenance-plans', MaintenancePlanController::class);
});
