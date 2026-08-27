<?php

namespace App\Support;

/**
 * The EXACT brand fonts the frontend uses (Geist / Outfit / Geist Mono /
 * Noto Sans Ethiopic), embedded as base64 @font-face rules so PDF renders
 * never depend on a font CDN and always match the HTML articles glyph for
 * glyph. Files are Google Fonts variable woff2s (one file spans all weights),
 * committed under resources/fonts.
 */
final class PdfFonts
{
    private static ?string $css = null;

    public static function css(): string
    {
        return self::$css ??= implode("\n", [
            self::face('Geist', 'geist.woff2', '100 900'),
            self::face('Geist Mono', 'geist-mono.woff2', '100 900'),
            self::face('Outfit', 'outfit.woff2', '100 900'),
            self::face('Noto Sans Ethiopic', 'noto-sans-ethiopic.woff2', '100 900'),
        ]);
    }

    private static function face(string $family, string $file, string $weights): string
    {
        $data = base64_encode((string) file_get_contents(resource_path("fonts/{$file}")));

        return "@font-face { font-family: '{$family}'; font-style: normal; font-weight: {$weights}; "
            ."src: url(data:font/woff2;base64,{$data}) format('woff2'); }";
    }
}
