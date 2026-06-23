<?php

use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Http\Middleware\RestrictedDocsAccess;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
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
})->middleware(['web', RestrictedDocsAccess::class])->name('docs');

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
})->middleware(['web', RestrictedDocsAccess::class])->name('docs.openapi');
