<?php

use Illuminate\Support\Facades\Route;

Route::prefix('v1')->as('api.v1.')->group(function (): void {
    require __DIR__.'/api/v1/auth.php';

    Route::get('/', fn () => response()->json([
        'name' => 'MaintOps Laravel API',
        'version' => 'v1',
    ]))->name('index');
});
