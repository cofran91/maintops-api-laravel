<?php

use App\Http\Controllers\Api\V1\Owners\OwnerController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('owners/export', [OwnerController::class, 'export'])
        ->name('owners.export');

    Route::post('owners/import', [OwnerController::class, 'import'])
        ->name('owners.import');

    Route::apiResource('owners', OwnerController::class);
});
