<?php

use Illuminate\Support\Facades\Route;

Route::prefix('v1')->as('api.v1.')->group(function (): void {
    require __DIR__.'/api/v1/auth.php';
    require __DIR__.'/api/v1/audits.php';
    require __DIR__.'/api/v1/users.php';
    require __DIR__.'/api/v1/vehicle-systems.php';
    require __DIR__.'/api/v1/owners.php';
    require __DIR__.'/api/v1/vehicles.php';
    require __DIR__.'/api/v1/workshops.php';
    require __DIR__.'/api/v1/maintenance-tasks.php';
    require __DIR__.'/api/v1/maintenance-plans.php';
    require __DIR__.'/api/v1/maintenance-orders.php';

    Route::get('/', fn () => response()->json([
        'name' => 'MaintOps Laravel API',
        'version' => 'v1',
    ]))->name('index');
});
