<?php

use Illuminate\Support\Facades\Route;

Route::prefix('v1')->as('api.v1.')->group(function (): void {
    Route::get('/', fn () => response()->json([
        'name' => 'MaintOps Laravel API',
        'version' => 'v1',
    ]))->name('index');
});
