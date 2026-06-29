<?php

namespace App\Support\Localization;

use Illuminate\Http\Request;

final class Locale
{
    /**
     * @return array<int, string>
     */
    public static function supported(): array
    {
        return array_values(config('localization.supported_locales', ['en', 'es']));
    }

    public static function default(): string
    {
        $configuredLocale = config('localization.default_locale', config('app.locale', 'en'));

        return self::normalize(is_string($configuredLocale) ? $configuredLocale : null)
            ?? 'en';
    }

    public static function normalize(?string $locale): ?string
    {
        if ($locale === null) {
            return null;
        }

        $normalized = strtolower(trim(str_replace('_', '-', $locale)));

        if ($normalized === '') {
            return null;
        }

        if (in_array($normalized, self::supported(), true)) {
            return $normalized;
        }

        $baseLocale = explode('-', $normalized, 2)[0];

        return in_array($baseLocale, self::supported(), true)
            ? $baseLocale
            : null;
    }

    public static function fromRequest(Request $request): string
    {
        foreach (['X-Locale', 'X-Language'] as $header) {
            $locale = self::normalize($request->header($header));

            if ($locale !== null) {
                return $locale;
            }
        }

        $preferredLanguage = $request->getPreferredLanguage(self::supported());

        return self::normalize($preferredLanguage) ?? self::default();
    }
}
