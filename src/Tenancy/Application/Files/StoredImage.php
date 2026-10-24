<?php

declare(strict_types=1);

namespace AulaX\Tenancy\Application\Files;

/**
 * A logo or signature read back from the institution's files.
 */
final readonly class StoredImage
{
    public function __construct(public string $contents, public string $mimeType) {}
}
