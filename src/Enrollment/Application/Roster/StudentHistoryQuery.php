<?php

declare(strict_types=1);

namespace AulaX\Enrollment\Application\Roster;

use AulaX\Enrollment\Application\EnrollmentAccess;
use AulaX\Enrollment\Application\Port\EnrollmentList;
use AulaX\Enrollment\Application\Port\EnrollmentPermissions;
use AulaX\Enrollment\Application\Port\SectionFact;
use AulaX\Enrollment\Application\Port\StructureFacts;
use AulaX\Enrollment\Domain\Enrollment;
use AulaX\Shared\Domain\AuthorizationDenied;

/**
 * The enrollments of a student, oldest first: year, grade, section, from,
 * until and why each ended, annulments included with who annulled them and
 * when (MAT-R04.6, R14.6).
 */
final readonly class StudentHistoryQuery
{
    public function __construct(
        private EnrollmentList $enrollments,
        private StructureFacts $structure,
        private EnrollmentPermissions $permissions,
    ) {}

    /**
     * @return list<HistoryEntry>
     *
     * @throws AuthorizationDenied
     */
    public function __invoke(int $studentId): array
    {
        EnrollmentAccess::ensureCanView($this->permissions);
        $sections = [];

        return array_map(function (Enrollment $enrollment) use (&$sections) {
            $year = $enrollment->schoolYearId();
            $sections[$year] ??= array_column(array_map(fn (SectionFact $section) => [$section->id, $section], $this->structure->sectionsOf($year)), 1, 0);
            $section = $sections[$year][$enrollment->placement()->sectionId] ?? null;
            $annulment = $enrollment->annulment();

            return new HistoryEntry(
                (int) $enrollment->id()?->value,
                $year,
                $section->gradeName ?? $enrollment->placement()->grade,
                $section->letter ?? '',
                $enrollment->period()->startsOn->format('Y-m-d'),
                $enrollment->period()->endsOn?->format('Y-m-d'),
                $enrollment->period()->endReason?->label(),
                $enrollment->endNote(),
                $annulment?->reason->label(),
                $annulment?->note,
                $annulment?->annulledBy,
                $annulment?->annulledAt->format('Y-m-d H:i'),
                $enrollment->isCurrent(),
            );
        }, $this->enrollments->historyOf($studentId));
    }
}
