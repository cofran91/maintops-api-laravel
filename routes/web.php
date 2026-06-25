<?php

use App\Http\Controllers\Web\InternalToolsAuthController;
use App\Http\Middleware\EnsureSuperAdminWebSession;
use Dedoc\Scramble\Scramble;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware('web')->group(function (): void {
    Route::get('/admin/login', [InternalToolsAuthController::class, 'create'])
        ->name('internal-tools.login');

    Route::post('/admin/login', [InternalToolsAuthController::class, 'store'])
        ->middleware('throttle:5,1')
        ->name('internal-tools.login.store');

    Route::post('/admin/logout', [InternalToolsAuthController::class, 'destroy'])
        ->name('internal-tools.logout');
});

Route::get('/docs', function () {
    $path = public_path('api.json');

    abort_unless(
        is_file($path),
        404,
        'The OpenAPI specification has not been exported. Run php artisan scramble:export.'
    );

    return view('scramble::docs', [
        'spec' => file_get_contents($path),
        'config' => Scramble::getGeneratorConfig('default'),
    ]);
})->middleware(['web', EnsureSuperAdminWebSession::class])->name('docs');

Route::get('/docs/api.json', function () {
    $path = public_path('api.json');

    abort_unless(
        is_file($path),
        404,
        'The OpenAPI specification has not been exported. Run php artisan scramble:export.'
    );

    return response()->file($path, [
        'Content-Type' => 'application/json',
    ]);
})->middleware(['web', EnsureSuperAdminWebSession::class])->name('docs.openapi');
