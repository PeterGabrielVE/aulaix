<?php

declare(strict_types=1);

namespace AulaX\Documents\Application\DTO;

/**
 * Institution header details and signatures for any rendered PDF (DOC-R05.1 –
 * R05.3). Images are embedded as inline data URIs (ADR 0015).
 */
final readonly class InstitutionHeaderData
{
    /**
     * @param  list<string>  $missingNotices
     */
    public function __construct(
        public string $name,
        public ?string $deaCode,
        public ?string $rif,
        public ?string $location,
        public ?string $address,
        public ?string $phone,
        public ?string $email,
        public ?string $logoDataUri,
        public SignatureBlockData $directorSignature,
        public ?SignatureBlockData $secretarySignature,
        public array $missingNotices = [],
    ) {}
}
