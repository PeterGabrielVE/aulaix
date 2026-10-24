<?php

declare(strict_types=1);

namespace AulaX\Students\Application\FindGuardian;

final readonly class GuardianView
{
    public function __construct(
        public int $id,
        public string $givenNames,
        public string $familyNames,
        public string $documentType,
        public string $document,
        public string $phone,
        public ?string $email,
        public ?string $address,
    ) {}
}
