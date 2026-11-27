<?php

declare(strict_types=1);

namespace AulaX\Students\Application\UpdateGuardian;

final readonly class UpdateGuardianCommand
{
    public function __construct(
        public int $guardianId,
        public ?string $givenNames,
        public ?string $familyNames,
        public ?string $documentType,
        public ?string $document,
        public ?string $phone,
        public ?string $email,
        public ?string $address,
    ) {}
}
