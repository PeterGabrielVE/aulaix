<?php

declare(strict_types=1);

namespace AulaX\AcademicStructure\Application;

use AulaX\AcademicCalendar\Application\SchoolYearLookup;
use AulaX\AcademicStructure\Application\Port\AssignmentDirectory;
use AulaX\IdentityAccess\Application\Account\DeletionBlocker;
use AulaX\IdentityAccess\Application\Port\InstitutionScope;
use AulaX\Tenancy\Application\TenantContext;

/**
 * EAC-R09.1 (IDG-R08.4): an account can not be deleted while it teaches a
 * subject or guides a section in the active school year of any of its
 * institutions; the institution must replace it first. Checked in each
 * institution with it active, under its Row Level Security.
 */
final readonly class ActiveAssignmentBlocker implements DeletionBlocker
{
    public function __construct(
        private InstitutionScope $institutions,
        private SchoolYearLookup $years,
        private AssignmentDirectory $assignments,
    ) {}

    public function reasonsFor(int $accountId): array
    {
        $reasons = [];

        $this->institutions->forEachInstitutionOf($accountId, function (TenantContext $institution) use ($accountId, &$reasons) {
            $year = $this->years->activeYear();

            if ($year !== null && $this->assignments->sectionIdsOf($accountId, $year) !== []) {
                $reasons[] = "Tienes clases o una sección a tu cargo en el año escolar activo de {$institution->name}: pide al plantel que te reemplace antes de eliminar tu cuenta.";
            }
        });

        return $reasons;
    }
}
