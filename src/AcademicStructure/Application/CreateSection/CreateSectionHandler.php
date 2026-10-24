<?php

declare(strict_types=1);

namespace AulaX\AcademicStructure\Application\CreateSection;

use AulaX\AcademicCalendar\Application\CalendarLock;
use AulaX\AcademicStructure\Application\Port\StructurePermissions;
use AulaX\AcademicStructure\Application\StructureAccess;
use AulaX\AcademicStructure\Application\StructureAudit;
use AulaX\AcademicStructure\Application\StructureLoader;
use AulaX\AcademicStructure\Domain\Capacity;
use AulaX\AcademicStructure\Domain\Grade;
use AulaX\AcademicStructure\Domain\SchoolYearStructureRepository;
use AulaX\AcademicStructure\Domain\SectionLetter;
use AulaX\Audit\Application\AuditTrail;
use AulaX\Shared\Application\TransactionManager;

/**
 * A manager opens a section of a grade in a school year, with its letter and
 * capacity (EAC-R02.1 – R02.4, R02.8, R02.9). Under the calendar lock, so
 * the year does not close meanwhile (ADR 0007, EAC-R07).
 */
final readonly class CreateSectionHandler
{
    public function __construct(
        private StructureLoader $loader,
        private SchoolYearStructureRepository $structures,
        private StructurePermissions $permissions,
        private CalendarLock $lock,
        private AuditTrail $audit,
        private TransactionManager $transactions,
    ) {}

    /**
     * @return int The section's id.
     */
    public function __invoke(CreateSectionCommand $command): int
    {
        StructureAccess::ensureCanManage($this->permissions);
        $grade = Grade::fromInput($command->grade);
        $letter = SectionLetter::fromString($command->letter);
        $capacity = Capacity::of($command->capacity, $command->capacityReason, $grade);

        return $this->transactions->run(function () use ($command, $grade, $letter, $capacity) {
            $this->lock->lockInstitutionCalendar();
            $structure = $this->loader->forYear($command->schoolYearId);

            $section = $structure->createSection($grade, $letter, $capacity);
            $this->structures->save($structure);
            $this->audit->record(StructureAudit::sectionCreated($command->schoolYearId, $section));

            return (int) $section->id();
        });
    }
}
