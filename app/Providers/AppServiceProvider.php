<?php

namespace App\Providers;

use App\Models\MaintenanceOrder;
use App\Models\MaintenanceOrderItem;
use App\Observers\MaintenanceOrderItemObserver;
use App\Observers\MaintenanceOrderObserver;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\SecurityScheme;
use Dedoc\Scramble\Support\RouteInfo;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        Scramble::ignoreDefaultRoutes();
    }

    public function boot(): void
    {
        MaintenanceOrder::observe(MaintenanceOrderObserver::class);
        MaintenanceOrderItem::observe(MaintenanceOrderItemObserver::class);

        RateLimiter::for('analytics-initial-sync', fn ($request): Limit => Limit::perMinute(30)
            ->by((string) $request->ip()));

        ResetPassword::createUrlUsing(function (object $notifiable, string $token): string {
            $baseUrl = rtrim((string) config('app.frontend_password_reset_url'), '/');

            return $baseUrl.'?'.http_build_query([
                'token' => $token,
                'email' => $notifiable->getEmailForPasswordReset(),
            ]);
        });

        Scramble::routes(fn (Route $route): bool => str_starts_with($route->uri, 'api/v1'));

        Scramble::configure()
            ->withDocumentTransformers(function (OpenApi $openApi): void {
                $openApi->secure(
                    SecurityScheme::http('bearer')
                        ->setDescription('Bearer token issued by Laravel Sanctum.')
                );
            });

        Scramble::resolveTagsUsing(function (RouteInfo $routeInfo, Operation $operation): array {
            $uri = $routeInfo->route->uri();

            return match (true) {
                Str::startsWith($uri, 'api/v1/auth') => ['Authentication'],
                Str::startsWith($uri, 'api/v1/audits') => ['Audit'],
                Str::startsWith($uri, 'api/v1/users') => ['User Management'],
                Str::startsWith($uri, 'api/v1/vehicle-systems') => ['Catalogs'],
                Str::startsWith($uri, 'api/v1/owners') => ['Catalogs'],
                Str::startsWith($uri, 'api/v1/vehicles') => ['Catalogs'],
                Str::startsWith($uri, 'api/v1/workshops') => ['Catalogs'],
                Str::startsWith($uri, 'api/v1/maintenance-tasks') => ['Maintenance'],
                Str::startsWith($uri, 'api/v1/maintenance-plans') => ['Maintenance'],
                Str::startsWith($uri, 'api/v1/maintenance-orders') => ['Maintenance'],
                Str::startsWith($uri, 'api/v1/maintenance-order-items') => ['Maintenance'],
                Str::startsWith($uri, 'api/v1/dashboard') => ['Dashboard'],
                Str::startsWith($uri, 'api/v1/internal/analytics') => ['Integrations'],
                default => ['General'],
            };
        });
    }
}
