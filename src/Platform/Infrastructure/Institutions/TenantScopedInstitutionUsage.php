<?php

declare(strict_types=1);

namespace AulaX\Platform\Infrastructure\Institutions;

use AulaX\AcademicCalendar\Application\CalendarContext\CalendarContextQuery;
use AulaX\Audit\Application\ListEntries\LastActivityQuery;
use AulaX\IdentityAccess\Application\Members\MemberCountsQuery;
use AulaX\Platform\Application\Port\InstitutionUsage;
use AulaX\Platform\Infrastructure\TenantScopedReader;
use AulaX\Students\Application\ActiveStudentCountQuery;
use AulaX\Tenancy\Application\InstitutionFiles\UsedStorageQuery;

/**
 * One institution's figures, read within its own RLS by the bounded reader
 * (ADR 0010, SPEC-014 V1) and only through the aggregate queries (V2):
 * numbers, never a person.
 */
final readonly class TenantScopedInstitutionUsage implements InstitutionUsage
{
    public function __construct(
        private TenantScopedReader $reader,
        private MemberCountsQuery $members,
        private ActiveStudentCountQuery $students,
        private LastActivityQuery $lastActivity,
        private UsedStorageQuery $storage,
        private CalendarContextQuery $calendar,
    ) {}

    public function of(int $institutionId): array
    {
        return $this->reader->read($institutionId, fn () => [
            'members' => ($this->members)(),
            'activeStudents' => ($this->students)(),
            'lastActivity' => ($this->lastActivity)()?->format(DATE_ATOM),
            'usedBytes' => ($this->storage)(),
            'activeYear' => ($this->calendar)()->activeYearName,
        ]);
    }
}
