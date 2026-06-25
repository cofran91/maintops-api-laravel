<?php

use App\Http\Controllers\Api\V1\Audits\AuditController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('audits', [AuditController::class, 'index'])->name('audits.index');
});
