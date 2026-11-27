<?php

declare(strict_types=1);

namespace AulaX\AcademicStructure\Application\RemoveCoordinator;

use AulaX\AcademicCalendar\Application\CalendarLock;
use AulaX\AcademicStructure\Application\Port\StructurePermissions;
use AulaX\AcademicStructure\Application\StructureAccess;
use AulaX\AcademicStructure\Application\StructureAudit;
use AulaX\AcademicStructure\Application\StructureLoader;
use AulaX\AcademicStructure\Domain\SchoolYearStructureRepository;
use AulaX\Audit\Application\AuditTrail;
use AulaX\Shared\Application\TransactionManager;

/**
 * A manager removes a coordinator from a level in a school year (EAC-R06.5).
 */
final readonly class RemoveCoordinatorHandler
{
    public function __construct(
        private StructureLoader $loader,
        private SchoolYearStructureRepository $structures,
        private StructurePermissions $permissions,
        private CalendarLock $lock,
        private AuditTrail $audit,
        private TransactionManager $transactions,
    ) {}

    public function __invoke(int $schoolYearId, int $accountId, string $level): void
    {
        StructureAccess::ensureCanManage($this->permissions);

        $this->transactions->run(function () use ($schoolYearId, $accountId, $level) {
            $this->lock->lockInstitutionCalendar();
            $structure = $this->loader->forYear($schoolYearId);

            $structure->removeCoordinator($accountId, $level);
            $this->structures->save($structure);
            $this->audit->record(StructureAudit::coordinatorRemoved($schoolYearId, $accountId, $level));
        });
    }
}
