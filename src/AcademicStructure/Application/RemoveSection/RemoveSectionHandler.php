<?php

declare(strict_types=1);

namespace AulaX\AcademicStructure\Application\RemoveSection;

use AulaX\AcademicCalendar\Application\CalendarLock;
use AulaX\AcademicStructure\Application\Port\StructurePermissions;
use AulaX\AcademicStructure\Application\RemovalGuards;
use AulaX\AcademicStructure\Application\StructureAccess;
use AulaX\AcademicStructure\Application\StructureAudit;
use AulaX\AcademicStructure\Application\StructureLoader;
use AulaX\AcademicStructure\Domain\SchoolYearStructureRepository;
use AulaX\Audit\Application\AuditTrail;
use AulaX\Shared\Application\TransactionManager;

/**
 * A manager removes a section with its teacher assignments (EAC-R02.7);
 * not from an active year while it has students (EAC-R07.3).
 */
final readonly class RemoveSectionHandler
{
    public function __construct(
        private StructureLoader $loader,
        private SchoolYearStructureRepository $structures,
        private RemovalGuards $guards,
        private StructurePermissions $permissions,
        private CalendarLock $lock,
        private AuditTrail $audit,
        private TransactionManager $transactions,
    ) {}

    public function __invoke(int $sectionId): void
    {
        StructureAccess::ensureCanManage($this->permissions);

        $this->transactions->run(function () use ($sectionId) {
            $this->lock->lockInstitutionCalendar();
            $structure = $this->loader->forSection($sectionId);

            $removed = $structure->removeSection($sectionId, $this->guards->sectionInUse($sectionId), $this->guards->hasEnrollments($sectionId));
            $this->structures->save($structure);
            $this->audit->record(StructureAudit::sectionRemoved($removed));
        });
    }
}
