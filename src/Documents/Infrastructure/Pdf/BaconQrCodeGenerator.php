<?php

declare(strict_types=1);

namespace AulaX\Documents\Infrastructure\Pdf;

use AulaX\Documents\Application\Port\QrCodeGenerator;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

/**
 * Generates QR codes in memory as SVG data URIs using `bacon/bacon-qr-code`
 * (already installed in composer.json; ADR 0015).
 */
final readonly class BaconQrCodeGenerator implements QrCodeGenerator
{
    public function toDataUri(string $url): string
    {
        $writer = new Writer(new ImageRenderer(new RendererStyle(120, 1), new SvgImageBackEnd));
        $svg = $writer->writeString($url);

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }
}
