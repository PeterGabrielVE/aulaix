<?php

declare(strict_types=1);

namespace AulaX\Students\Application\StudentForDocuments;

/**
 * Public read projection of a student for SPEC-012 Documents (A-06).
 */
final readonly class StudentDocumentProfile
{
    public function __construct(
        public int $id,
        public string $givenNames,
        public string $familyNames,
        public string $fullName,
        public string $identification,
        public string $status,
        public ?string $latestWithdrawalDate,
        public ?string $latestWithdrawalReason,
    ) {}
}
