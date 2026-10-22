<?php

declare(strict_types=1);

namespace AulaX\AcademicStructure\Application\Port;

/**
 * The people of the open institution who can teach, guide or coordinate,
 * as IdentityAccess knows them: active members with the role, by any of
 * their roles (EAC-R05.2, R06.2, R06.4, R12.3).
 */
interface StaffDirectory
{
    public const string TEACHER = 'Docente';

    public const string COORDINATOR = 'Coordinador';

    public function isActiveWithRole(int $accountId, string $role): bool;

    /**
     * @return list<array{id: int, name: string}> By name.
     */
    public function candidates(string $role): array;

    /**
     * The name of each of these people and whether they are still active
     * members (EAC-R08.5); an account it can not see is named generically.
     *
     * @param  list<int>  $accountIds
     * @return array<int, array{name: string, active: bool}>
     */
    public function describe(array $accountIds): array;
}
