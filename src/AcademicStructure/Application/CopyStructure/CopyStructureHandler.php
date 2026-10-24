<?php

declare(strict_types=1);

namespace AulaX\AcademicStructure\Application\CopyStructure;

use AulaX\AcademicCalendar\Application\CalendarLock;
use AulaX\AcademicCalendar\Application\SchoolYearLookup;
use AulaX\AcademicStructure\Application\Port\StructurePermissions;
use AulaX\AcademicStructure\Application\StructureAccess;
use AulaX\AcademicStructure\Application\StructureAudit;
use AulaX\AcademicStructure\Application\StructureLoader;
use AulaX\AcademicStructure\Domain\SchoolYearStructureRepository;
use AulaX\Audit\Application\AuditTrail;
use AulaX\Shared\Application\TransactionManager;
use AulaX\Shared\Domain\BusinessRuleViolation;

/**
 * A manager starts a planned year from the structure of another one, the
 * year before by default (EAC-R04): sections and curriculum, not people.
 */
final readonly class CopyStructureHandler
{
    public function __construct(
        private StructureLoader $loader,
        private SchoolYearStructureRepository $structures,
        private SchoolYearLookup $years,
        private StructurePermissions $permissions,
        private CalendarLock $lock,
        private AuditTrail $audit,
        private TransactionManager $transactions,
    ) {}

    public function __invoke(int $toSchoolYearId, ?int $fromSchoolYearId = null): void
    {
        StructureAccess::ensureCanManage($this->permissions);

        $this->transactions->run(function () use ($toSchoolYearId, $fromSchoolYearId) {
            $this->lock->lockInstitutionCalendar();
            $from = $fromSchoolYearId ?? $this->years->previousOf($toSchoolYearId)
                ?? throw BusinessRuleViolation::onField('school_year', 'No hay un año escolar anterior del que copiar.');

            $target = $this->loader->forYear($toSchoolYearId);
            $target->copyFrom($this->loader->forYear($from));

            $this->structures->save($target);
            $this->audit->record(StructureAudit::copied($from, $toSchoolYearId));
        });
    }
}
