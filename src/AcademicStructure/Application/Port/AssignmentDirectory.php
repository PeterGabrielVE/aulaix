<?php

declare(strict_types=1);

namespace AulaX\AcademicStructure\Application\Port;

/**
 * Fast reads of the teaching assignments of the open institution, for the
 * questions other modules ask on every request (EAC-R08.4, NF02) and for
 * the account deletion block (EAC-R09.1).
 */
interface AssignmentDirectory
{
    public function teaches(int $accountId, int $sectionId, int $subjectId, int $schoolYearId): bool;

    /**
     * Whether the person is the guide of the section of that year.
     */
    public function guides(int $accountId, int $sectionId, int $schoolYearId): bool;

    /**
     * The sections of the year the person teaches in or guides, by id.
     *
     * @return list<int>
     */
    public function sectionIdsOf(int $accountId, int $schoolYearId): array;
}
