<?php

declare(strict_types=1);

namespace AulaX\Students\Application\GuardianPortal;

use AulaX\Shared\Domain\AuthorizationDenied;
use AulaX\Students\Application\ListStudents\StudentListItem;
use AulaX\Students\Application\StudentDirectory;
use AulaX\Students\Domain\Guardian\GuardianRepository;

/**
 * The students an account represents in the open institution (EST-R08.2).
 * Access comes from the account's link to a guardian, not from a role.
 */
final readonly class GuardianStudentsQuery
{
    public function __construct(
        private GuardianRepository $guardians,
        private StudentDirectory $directory,
    ) {}

    /**
     * @return list<StudentListItem>
     *
     * @throws AuthorizationDenied When the account represents no student here.
     */
    public function __invoke(int $accountId): array
    {
        return $this->directory->representedBy(LinkedGuardian::of($this->guardians, $accountId));
    }
}
