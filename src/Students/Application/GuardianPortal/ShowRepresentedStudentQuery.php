<?php

declare(strict_types=1);

namespace AulaX\Students\Application\GuardianPortal;

use AulaX\Shared\Domain\AuthorizationDenied;
use AulaX\Students\Application\ListStudents\StudentListItem;
use AulaX\Students\Application\ShowStudent\StudentView;
use AulaX\Students\Application\StudentDirectory;
use AulaX\Students\Application\StudentNotFound;
use AulaX\Students\Domain\Guardian\GuardianRepository;

/**
 * The basic record of one of the account's represented students, never its
 * health data (EST-R08.2). Any other student does not exist for it
 * (EST-R08.3).
 */
final readonly class ShowRepresentedStudentQuery
{
    public function __construct(
        private GuardianRepository $guardians,
        private StudentDirectory $directory,
    ) {}

    /**
     * @throws AuthorizationDenied When the account represents no student here.
     * @throws StudentNotFound
     */
    public function __invoke(int $accountId, int $studentId): StudentView
    {
        $guardianId = LinkedGuardian::of($this->guardians, $accountId);
        $represented = array_map(fn (StudentListItem $student) => $student->id, $this->directory->representedBy($guardianId));

        if (! in_array($studentId, $represented, true)) {
            throw StudentNotFound::create();
        }

        return $this->directory->student($studentId, includeHealth: false) ?? throw StudentNotFound::create();
    }
}
