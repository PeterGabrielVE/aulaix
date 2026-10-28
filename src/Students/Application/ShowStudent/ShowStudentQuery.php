<?php

declare(strict_types=1);

namespace AulaX\Students\Application\ShowStudent;

use AulaX\Students\Application\Port\StudentPermissions;
use AulaX\Students\Application\StudentAccess;
use AulaX\Students\Application\StudentDirectory;
use AulaX\Students\Application\StudentNotFound;
use AulaX\Students\Application\StudentVisibility;

/**
 * A student's record (EST-R05.4). The health data only when asked and only
 * for whoever manages students; the others get the warning (EST-R04.2,
 * R04.3). One the visibility rules hide does not exist (SPEC-010 MAT-R08.2).
 */
final readonly class ShowStudentQuery
{
    public function __construct(
        private StudentDirectory $directory,
        private StudentPermissions $permissions,
        private StudentVisibility $visibility = new StudentVisibility([]),
    ) {}

    /**
     * @throws StudentNotFound
     */
    public function __invoke(int $studentId, bool $includeHealth): StudentView
    {
        StudentAccess::ensureCanView($this->permissions);

        if (! $this->visibility->sees($studentId)) {
            throw StudentNotFound::create();
        }

        $withHealth = $includeHealth && $this->permissions->canManage();

        return $this->directory->student($studentId, $withHealth) ?? throw StudentNotFound::create();
    }
}
