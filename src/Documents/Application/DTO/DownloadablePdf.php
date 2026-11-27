<?php

declare(strict_types=1);

namespace AulaX\Documents\Application\DTO;

/**
 * Binary PDF payload returned by issue/download use cases (never streamed,
 * ADR 0011).
 */
final readonly class DownloadablePdf
{
    public function __construct(
        public string $bytes,
        public string $filename,
        public ?int $documentId = null,
        public int $documentCount = 1,
    ) {}
}
