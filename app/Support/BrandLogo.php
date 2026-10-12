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

    public static function publicPath(): string
    {
        foreach (self::candidatePaths() as $relative) {
            if (is_file(public_path($relative))) {
                return public_path($relative);
            }
        }

        return public_path('logo.png');
    }

    public static function dataUri(): string
    {
        $path = self::publicPath();
        $mime = match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'jpg', 'jpeg' => 'image/jpeg',
            'svg' => 'image/svg+xml',
            'webp' => 'image/webp',
            default => 'image/png',
        };

        return 'data:' . $mime . ';base64,' . base64_encode((string) file_get_contents($path));
    }

    /**
     * @return list<string>
     */
    public static function candidatePaths(): array
    {
        $configured = ltrim((string) config('app.logo', 'logo.png'), '/');

        return array_values(array_unique(array_filter([
            $configured,
            'iconmu.jpeg',
            'icon.png',
            'logo.png',
            'logo.jpg',
            'logo.jpeg',
            'images/logo.png',
        ])));
    }
}
