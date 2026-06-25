<?php

use App\Http\Controllers\Api\V1\Integrations\AnalyticsInitialSyncController;
use App\Http\Middleware\EnsureAnalyticsServiceRequest;
use Illuminate\Support\Facades\Route;

Route::prefix('internal/analytics')
    ->middleware([EnsureAnalyticsServiceRequest::class, 'throttle:analytics-initial-sync'])
    ->group(function (): void {
        Route::get('initial-sync/{resource}', AnalyticsInitialSyncController::class)
            ->whereIn('resource', [
                'workshops',
                'technicians',
                'maintenance-tasks',
                'maintenance-orders',
                'maintenance-order-items',
            ])
            ->name('internal.analytics.initial-sync');
    });
