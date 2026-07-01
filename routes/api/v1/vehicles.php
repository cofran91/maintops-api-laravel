<?php

use App\Http\Controllers\Api\V1\Vehicles\VehicleController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('vehicles/export', [VehicleController::class, 'export'])
        ->name('vehicles.export');

    Route::post('vehicles/import', [VehicleController::class, 'import'])
        ->name('vehicles.import');

    Route::apiResource('vehicles', VehicleController::class);
});
