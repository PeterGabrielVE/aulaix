<?php

declare(strict_types=1);

namespace AulaX\Documents\Application\Port;

/**
 * Generates an inline data-URI QR code for a public verification URL
 * (SPEC-012 DOC-R07.1, ADR 0015).
 */
interface QrCodeGenerator
{
    public function toDataUri(string $url): string;
}
