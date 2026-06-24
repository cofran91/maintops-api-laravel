<?php

use App\Http\Controllers\Api\V1\MaintenanceTasks\MaintenanceTaskController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function (): void {
    Route::apiResource('maintenance-tasks', MaintenanceTaskController::class);
});
