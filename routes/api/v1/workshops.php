<?php

use App\Http\Controllers\Api\V1\Workshops\WorkshopController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('workshops/export', [WorkshopController::class, 'export'])
        ->name('workshops.export');

    Route::post('workshops/import', [WorkshopController::class, 'import'])
        ->name('workshops.import');

    Route::apiResource('workshops', WorkshopController::class);
});
