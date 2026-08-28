<?php

namespace App\Support;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

class QrCodeGenerator
{
    public static function svg(string $content, int $size = 220): string
    {
        $writer = new Writer(new ImageRenderer(
            new RendererStyle($size),
            new SvgImageBackEnd
        ));

        $svg = $writer->writeString($content);

        return preg_replace('/^<\?xml[^>]*>\s*/', '', $svg) ?? $svg;
    }
}
