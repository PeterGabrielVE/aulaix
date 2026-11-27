<?php

declare(strict_types=1);

namespace AulaX\AcademicStructure\Application\AssignTeacher;

use AulaX\AcademicCalendar\Application\CalendarLock;
use AulaX\AcademicStructure\Application\Port\StaffDirectory;
use AulaX\AcademicStructure\Application\Port\StructurePermissions;
use AulaX\AcademicStructure\Application\StructureAccess;
use AulaX\AcademicStructure\Application\StructureAudit;
use AulaX\AcademicStructure\Application\StructureLoader;
use AulaX\AcademicStructure\Domain\SchoolYearStructureRepository;
use AulaX\Audit\Application\AuditTrail;
use AulaX\Shared\Application\TransactionManager;
use AulaX\Shared\Domain\BusinessRuleViolation;

/**
 * A manager says who teaches a subject in a section, or that nobody does
 * (EAC-R05.1 – R05.5, R05.7): only an active teacher of the institution
 * (EAC-R05.2, R12.3), recorded with the old and new teacher (EAC-R11.1).
 */
final readonly class AssignTeacherHandler
{
    public function __construct(
        private StructureLoader $loader,
        private SchoolYearStructureRepository $structures,
        private StaffDirectory $staff,
        private StructurePermissions $permissions,
        private CalendarLock $lock,
        private AuditTrail $audit,
        private TransactionManager $transactions,
    ) {}

    public function __invoke(int $sectionId, int $subjectId, ?int $accountId): void
    {
        StructureAccess::ensureCanManage($this->permissions);

        if ($accountId !== null && ! $this->staff->isActiveWithRole($accountId, StaffDirectory::TEACHER)) {
            throw BusinessRuleViolation::onField('account_id', 'Esa persona no es docente activo del plantel.');
        }

        $this->transactions->run(function () use ($sectionId, $subjectId, $accountId) {
            $this->lock->lockInstitutionCalendar();
            $structure = $this->loader->forSection($sectionId);
            $section = $structure->section($sectionId);
            $before = $section->teachers()[$subjectId] ?? null;

            $accountId === null
                ? $structure->removeTeacher($sectionId, $subjectId)
                : $structure->assignTeacher($sectionId, $subjectId, $accountId);

            if ($before === $accountId) {
                return;
            }

            $this->structures->save($structure);
            $this->audit->record(StructureAudit::teacherAssigned($section, $subjectId, $before, $accountId));
        });
    }
}
