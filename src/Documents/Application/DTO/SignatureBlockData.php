<?php

declare(strict_types=1);

namespace AulaX\Documents\Application\DTO;

/**
 * Signature block printed at the foot of a document (SPEC-012 DOC-R05.2,
 * R05.3). When imageDataUri is null, a blank physical signature line renders.
 */
final readonly class SignatureBlockData
{
    public function __construct(
        public string $defaultLabel,
        public ?string $signerName,
        public ?string $signerTitle,
        public ?string $imageDataUri,
    ) {}
}
