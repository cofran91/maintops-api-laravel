<?php

use App\Http\Middleware\SetApiLocale;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prependToGroup('api', SetApiLocale::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request): bool => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'success' => false,
                'message' => __('api.exceptions.unauthenticated'),
            ], 401);
        });

        $exceptions->render(function (AuthorizationException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'success' => false,
                'message' => __('api.exceptions.forbidden'),
            ], 403);
        });

        $exceptions->render(function (HttpException $e, Request $request) {
            if (! $request->is('api/*') || $e->getStatusCode() !== 403) {
                return null;
            }

            return response()->json([
                'success' => false,
                'message' => __('api.exceptions.forbidden'),
            ], 403);
        });

        $exceptions->render(function (ValidationException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'success' => false,
                'message' => __('api.exceptions.validation'),
                'errors' => $e->errors(),
            ], $e->status);
        });

        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'success' => false,
                'message' => __('api.exceptions.not_found'),
            ], 404);
        });

        $exceptions->render(function (Throwable $e, Request $request) {
            if (
                ! $request->is('api/*')
                || $e instanceof AuthenticationException
                || $e instanceof AuthorizationException
                || $e instanceof HttpExceptionInterface
                || $e instanceof HttpResponseException
                || $e instanceof ValidationException
            ) {
                return null;
            }

            return response()->json([
                'success' => false,
                'message' => __('api.exceptions.server_error'),
            ], 500);
        });
    })->create();
