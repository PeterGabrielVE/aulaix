<?php

declare(strict_types=1);

namespace AulaX\AcademicStructure\Application\AssignCoordinator;

use AulaX\AcademicCalendar\Application\CalendarLock;
use AulaX\AcademicStructure\Application\Port\StaffDirectory;
use AulaX\AcademicStructure\Application\Port\StructurePermissions;
use AulaX\AcademicStructure\Application\StructureAccess;
use AulaX\AcademicStructure\Application\StructureAudit;
use AulaX\AcademicStructure\Application\StructureLoader;
use AulaX\AcademicStructure\Domain\CoordinatorAssignment;
use AulaX\AcademicStructure\Domain\Grade;
use AulaX\AcademicStructure\Domain\SchoolYearStructureRepository;
use AulaX\Audit\Application\AuditTrail;
use AulaX\Shared\Application\TransactionManager;
use AulaX\Shared\Domain\BusinessRuleViolation;

/**
 * A manager assigns a coordinator to a level of the institution in a
 * school year, all of it or some of its grades (EAC-R06.3, R06.4, R06.6):
 * only an active coordinator of the institution.
 */
final readonly class AssignCoordinatorHandler
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

    /**
     * @param  list<string>  $grades  Empty: the whole level.
     */
    public function __invoke(int $schoolYearId, int $accountId, string $level, array $grades): void
    {
        StructureAccess::ensureCanManage($this->permissions);

        if (! $this->staff->isActiveWithRole($accountId, StaffDirectory::COORDINATOR)) {
            throw BusinessRuleViolation::onField('account_id', 'Esa persona no es coordinador activo del plantel.');
        }

        $assignment = new CoordinatorAssignment($accountId, $level, array_map(fn (string $grade) => Grade::fromInput($grade, 'grades'), array_values($grades)));

        $this->transactions->run(function () use ($schoolYearId, $assignment) {
            $this->lock->lockInstitutionCalendar();
            $structure = $this->loader->forYear($schoolYearId);

            $structure->assignCoordinator($assignment);
            $this->structures->save($structure);
            $this->audit->record(StructureAudit::coordinatorAssigned(
                $schoolYearId,
                $assignment->accountId,
                $assignment->level,
                array_map(fn (Grade $grade) => $grade->value, $assignment->grades),
            ));
        });
    }
}
