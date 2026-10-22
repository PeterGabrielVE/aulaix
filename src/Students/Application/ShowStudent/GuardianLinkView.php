<?php

declare(strict_types=1);

namespace AulaX\Students\Application\ShowStudent;

/**
 * A guardian of the student, as its record shows it.
 */
final readonly class GuardianLinkView
{
    public function __construct(
        public int $guardianId,
        public string $givenNames,
        public string $familyNames,
        public string $documentType,
        public string $document,
        public string $phone,
        public ?string $email,
        public ?string $address,
        public string $relationship,
        public bool $isLegal,
        public bool $isPrimary,
        public bool $hasAccount,
    ) {}
}
