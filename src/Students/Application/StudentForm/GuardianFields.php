<?php

declare(strict_types=1);

namespace AulaX\Students\Application\StudentForm;

/**
 * One guardian of the student form: an existing one by its id, or a new one
 * with its data; and the link to the student.
 */
final readonly class GuardianFields
{
    public function __construct(
        public ?int $guardianId,
        public ?string $givenNames,
        public ?string $familyNames,
        public ?string $documentType,
        public ?string $document,
        public ?string $phone,
        public ?string $email,
        public ?string $address,
        public string $relationship,
        public bool $isLegal,
        public bool $isPrimary,
    ) {}
}
