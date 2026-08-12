<?php

namespace App\Support;

final class BrandLogo
{
    public static function assetUrl(): string
    {
        foreach (self::candidatePaths() as $relative) {
            if (is_file(public_path($relative))) {
                return asset($relative);
            }
        }

        return asset('logo.png');
    }

    /**
     * @return list<string>
     */
    public static function candidatePaths(): array
    {
        $configured = ltrim((string) config('app.logo', 'logo.png'), '/');

        return array_values(array_unique(array_filter([
            $configured,
            'logo.png',
            'logo.jpg',
            'logo.jpeg',
            'logo-bijur.jpeg',
            'images/logo.png',
        ])));
    }
}
