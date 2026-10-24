<?php

declare(strict_types=1);

namespace AulaX\Tenancy\Application\ShowInstitution;

final readonly class SignatureView
{
    public function __construct(
        public string $role,
        public string $signerName,
        public string $signerTitle,
    ) {}
}
