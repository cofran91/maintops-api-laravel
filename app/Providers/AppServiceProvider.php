<?php

namespace App\Providers;

use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\SecurityScheme;
use Dedoc\Scramble\Support\RouteInfo;
use Illuminate\Routing\Route;
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
                Str::startsWith($uri, 'api/v1/users') => ['User Management'],
                Str::startsWith($uri, 'api/v1/vehicle-systems') => ['Catalogs'],
                Str::startsWith($uri, 'api/v1/owners') => ['Catalogs'],
                Str::startsWith($uri, 'api/v1/vehicles') => ['Catalogs'],
                default => ['General'],
            };
        });
    }
}
