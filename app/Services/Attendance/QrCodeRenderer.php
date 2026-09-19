<?php

namespace App\Services\Attendance;

use BaconQrCode\Renderer\Color\Rgb;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\Fill;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

/**
 * Renders QR payloads as inline SVG (server-side, no JS package) using the
 * same bacon/bacon-qr-code pipeline as the Fortify 2FA QR.
 */
class QrCodeRenderer
{
    /**
     * Inline SVG markup (XML prolog trimmed) ready for dangerouslySetInnerHTML.
     */
    public function svg(string $payload, int $size = 240): string
    {
        $svg = (new Writer(
            new ImageRenderer(
                new RendererStyle($size, 0, null, null, Fill::uniformColor(new Rgb(255, 255, 255), new Rgb(45, 55, 72))),
                new SvgImageBackEnd
            )
        ))->writeString($payload);

        return trim(substr($svg, (int) strpos($svg, "\n") + 1));
    }
}
