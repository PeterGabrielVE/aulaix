<?php

declare(strict_types=1);

namespace AulaX\Students\Application;

use AulaX\Students\Application\FindGuardian\GuardianView;
use AulaX\Students\Application\ListStudents\StudentListItem;
use AulaX\Students\Application\ListStudents\StudentPage;
use AulaX\Students\Application\ShowStudent\StudentView;
use AulaX\Students\Domain\Identity\GuardianDocument;

/**
 * Read side of the students of the open institution (A-08): lists and
 * records as views, without hydrating aggregates.
 */
interface StudentDirectory
{
    /**
     * By family and given names; the search matches names, family names,
     * the identification and former cédulas escolares (EST-R05.3, R02.8).
     */
    public function page(string $search, bool $includeWithdrawn, int $page, int $perPage): StudentPage;

    /**
     * The record, with its health data only when $includeHealth.
     */
    public function student(int $id, bool $includeHealth): ?StudentView;

    public function guardianByDocument(GuardianDocument $document): ?GuardianView;

    /**
     * The students this guardian represents, by family and given names
     * (EST-R08.2).
     *
     * @return list<StudentListItem>
     */
    public function representedBy(int $guardianId): array;

    /**
     * The active students whose only legal guardian is this one, as "Given
     * Family" names (EST-R08.6).
     *
     * @return list<string>
     */
    public function soleLegalGuardianOf(int $guardianId): array;
}
