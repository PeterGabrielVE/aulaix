<?php

declare(strict_types=1);

namespace AulaX\AcademicStructure\Application\SetGuide;

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
 * A manager names the guide teacher of a section, or removes it (EAC-R06.1,
 * R06.2, R06.5): only an active teacher of the institution.
 */
final readonly class SetGuideHandler
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

    public function __invoke(int $sectionId, ?int $accountId): void
    {
        StructureAccess::ensureCanManage($this->permissions);

        if ($accountId !== null && ! $this->staff->isActiveWithRole($accountId, StaffDirectory::TEACHER)) {
            throw BusinessRuleViolation::onField('account_id', 'Esa persona no es docente activo del plantel.');
        }

        $this->transactions->run(function () use ($sectionId, $accountId) {
            $this->lock->lockInstitutionCalendar();
            $structure = $this->loader->forSection($sectionId);
            $section = $structure->section($sectionId);
            $before = $section->guideAccountId();

            $structure->setGuide($sectionId, $accountId);

            if ($before === $accountId) {
                return;
            }

            $this->structures->save($structure);
            $this->audit->record(StructureAudit::guideChanged($section, $before));
        });
    }
}
