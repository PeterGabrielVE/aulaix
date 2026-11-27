<?php

declare(strict_types=1);

namespace AulaX\Students\Application\FindGuardian;

use AulaX\Shared\Domain\BusinessRuleViolation;
use AulaX\Students\Application\Port\StudentPermissions;
use AulaX\Students\Application\StudentAccess;
use AulaX\Students\Application\StudentDirectory;
use AulaX\Students\Domain\Identity\GuardianDocument;

/**
 * The guardian of the open institution with this document, so the student
 * form links it instead of duplicating it (EST-R03.3).
 */
final readonly class FindGuardianQuery
{
    public function __construct(
        private StudentDirectory $directory,
        private StudentPermissions $permissions,
    ) {}

    public function __invoke(string $documentType, string $document): ?GuardianView
    {
        StudentAccess::ensureCanManage($this->permissions);

        try {
            return $this->directory->guardianByDocument(GuardianDocument::fromInput($documentType, $document));
        } catch (BusinessRuleViolation) {
            return null;
        }
    }
}
