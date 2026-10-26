<?php

namespace App\Support;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

final class QrCode
{
    /**
     * Rendert den Inhalt als druckbares SVG (vektorbasiert, skaliert verlustfrei auf jede Etikettengröße).
     */
    public static function svg(string $content, int $size = 240): string
    {
        $writer = new Writer(new ImageRenderer(new RendererStyle($size, margin: 1), new SvgImageBackEnd));

        $svg = $writer->writeString($content);

        // Die XML-Deklaration stört beim Einbetten in HTML.
        return trim(preg_replace('/^<\?xml[^>]*\?>/', '', $svg) ?? $svg);
    }
}
