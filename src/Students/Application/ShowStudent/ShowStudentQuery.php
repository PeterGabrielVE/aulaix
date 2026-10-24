<?php

declare(strict_types=1);

namespace AulaX\Students\Application\ShowStudent;

use AulaX\Students\Application\Port\StudentPermissions;
use AulaX\Students\Application\StudentAccess;
use AulaX\Students\Application\StudentDirectory;
use AulaX\Students\Application\StudentNotFound;

/**
 * A student's record (EST-R05.4). The health data only when asked and only
 * for whoever manages students; the others get the warning (EST-R04.2,
 * R04.3).
 */
final readonly class ShowStudentQuery
{
    public function __construct(
        private StudentDirectory $directory,
        private StudentPermissions $permissions,
    ) {}

    /**
     * @throws StudentNotFound
     */
    public function __invoke(int $studentId, bool $includeHealth): StudentView
    {
        StudentAccess::ensureCanView($this->permissions);
        $withHealth = $includeHealth && $this->permissions->canManage();

        return $this->directory->student($studentId, $withHealth) ?? throw StudentNotFound::create();
    }
}
