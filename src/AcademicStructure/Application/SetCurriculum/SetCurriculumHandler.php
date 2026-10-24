<?php

declare(strict_types=1);

namespace AulaX\AcademicStructure\Application\SetCurriculum;

use AulaX\AcademicCalendar\Application\CalendarLock;
use AulaX\AcademicStructure\Application\Port\StructurePermissions;
use AulaX\AcademicStructure\Application\RemovalGuards;
use AulaX\AcademicStructure\Application\StructureAccess;
use AulaX\AcademicStructure\Application\StructureAudit;
use AulaX\AcademicStructure\Application\StructureLoader;
use AulaX\AcademicStructure\Domain\Grade;
use AulaX\AcademicStructure\Domain\SchoolYearStructureRepository;
use AulaX\Audit\Application\AuditTrail;
use AulaX\Catalogs\Application\Subjects;
use AulaX\Shared\Application\TransactionManager;

/**
 * A manager sets the subjects a grade sees in a school year, from the
 * catalog of its level (EAC-R03); a subject with grades stays in an active
 * year (EAC-R07.3).
 */
final readonly class SetCurriculumHandler
{
    public function __construct(
        private StructureLoader $loader,
        private SchoolYearStructureRepository $structures,
        private Subjects $subjects,
        private RemovalGuards $guards,
        private StructurePermissions $permissions,
        private CalendarLock $lock,
        private AuditTrail $audit,
        private TransactionManager $transactions,
    ) {}

    /**
     * @param  list<int>  $subjectIds
     */
    public function __invoke(int $schoolYearId, ?string $grade, array $subjectIds): void
    {
        StructureAccess::ensureCanManage($this->permissions);
        $grade = Grade::fromInput($grade);
        $subjectIds = array_values(array_map('intval', $subjectIds));

        $this->transactions->run(function () use ($schoolYearId, $grade, $subjectIds) {
            $this->lock->lockInstitutionCalendar();
            $structure = $this->loader->forYear($schoolYearId);
            $before = $structure->curriculumOf($grade);
            $removed = array_values(array_diff($before, $subjectIds));

            $structure->setCurriculum(
                $grade,
                $subjectIds,
                $this->subjects->levelsOf($subjectIds),
                $this->guards->subjectsInUse($schoolYearId, $grade, $removed),
            );

            if ($before === $subjectIds) {
                return;
            }

            $this->structures->save($structure);
            $this->audit->record(StructureAudit::curriculumChanged($schoolYearId, $grade, $before, $subjectIds));
        });
    }
}
