<?php

use App\Http\Controllers\Api\V1\MaintenanceOrders\MaintenanceOrderController;
use App\Http\Controllers\Api\V1\MaintenanceOrders\MaintenanceOrderItemController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function (): void {
    Route::apiResource('maintenance-orders', MaintenanceOrderController::class)
        ->except(['destroy']);

    Route::apiResource('maintenance-order-items', MaintenanceOrderItemController::class)
        ->except(['store', 'destroy']);
});
