<?php

declare(strict_types=1);

namespace AulaX\Tenancy\Application\ChangeSignature;

use AulaX\Tenancy\Application\Files\ImageUpload;
use AulaX\Tenancy\Domain\SignatureRole;

final readonly class ChangeSignatureCommand
{
    public function __construct(
        public SignatureRole $role,
        public string $signerName,
        public string $signerTitle,
        public ImageUpload $image,
    ) {}
}
