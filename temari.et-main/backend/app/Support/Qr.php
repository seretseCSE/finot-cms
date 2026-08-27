<?php

namespace App\Support;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

/**
 * Server-side QR codes for print templates: pure-PHP SVG (no imagick/gd),
 * embedded as a data URI so PDF renders stay fully self-contained.
 */
final class Qr
{
    public static function svgDataUri(string $content, int $size = 120): string
    {
        $renderer = new ImageRenderer(new RendererStyle($size, 0), new SvgImageBackEnd);
        $svg = (new Writer($renderer))->writeString($content);

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }
}
