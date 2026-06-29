<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\Localization\Locale;
use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

class SetApiLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        app()->setLocale($this->resolveLocale($request));

        return $next($request);
    }

    private function resolveLocale(Request $request): string
    {
        $userLocale = Locale::normalize($request->user()?->preferred_locale);

        if ($userLocale !== null) {
            return $userLocale;
        }

        $tokenLocale = $this->preferredLocaleFromBearerToken($request);

        if ($tokenLocale !== null) {
            return $tokenLocale;
        }

        return Locale::fromRequest($request);
    }

    private function preferredLocaleFromBearerToken(Request $request): ?string
    {
        $bearerToken = $request->bearerToken();

        if (! is_string($bearerToken) || $bearerToken === '') {
            return null;
        }

        $accessToken = PersonalAccessToken::findToken($bearerToken);
        $tokenable = $accessToken?->tokenable;

        if (! $tokenable instanceof User) {
            return null;
        }

        return Locale::normalize($tokenable->preferred_locale);
    }
}
