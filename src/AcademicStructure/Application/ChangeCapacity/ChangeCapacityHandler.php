<?php

declare(strict_types=1);

namespace AulaX\AcademicStructure\Application\ChangeCapacity;

use AulaX\AcademicCalendar\Application\CalendarLock;
use AulaX\AcademicStructure\Application\Port\StructurePermissions;
use AulaX\AcademicStructure\Application\RemovalGuards;
use AulaX\AcademicStructure\Application\StructureAccess;
use AulaX\AcademicStructure\Application\StructureAudit;
use AulaX\AcademicStructure\Application\StructureLoader;
use AulaX\AcademicStructure\Domain\Capacity;
use AulaX\AcademicStructure\Domain\SchoolYearStructureRepository;
use AulaX\Audit\Application\AuditTrail;
use AulaX\Shared\Application\TransactionManager;

/**
 * A manager changes a section's capacity (EAC-R02.5), never below the
 * students enrolled (EAC-R02.6), over the cap only with a reason (EAC-R02.8).
 */
final readonly class ChangeCapacityHandler
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

    public function __invoke(int $sectionId, mixed $capacity, ?string $reason): void
    {
        StructureAccess::ensureCanManage($this->permissions);

        $this->transactions->run(function () use ($sectionId, $capacity, $reason) {
            $this->lock->lockInstitutionCalendar();
            $structure = $this->loader->forSection($sectionId);
            $section = $structure->section($sectionId);
            $before = $section->capacity();

            $structure->changeCapacity($sectionId, Capacity::of($capacity, $reason, $section->grade()), $this->guards->enrolledIn($sectionId));
            $this->structures->save($structure);
            $this->audit->record(StructureAudit::capacityChanged($section, $before));
        });
    }
}
