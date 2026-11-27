<?php

declare(strict_types=1);

namespace AulaX\Enrollment\Infrastructure;

use AulaX\AcademicCalendar\Application\CalendarLock;
use AulaX\AcademicCalendar\Application\ListSchoolYears\ListSchoolYearsHandler;
use AulaX\AcademicCalendar\Application\ListSchoolYears\SchoolYearView;
use AulaX\AcademicCalendar\Application\SchoolYearLookup;
use AulaX\Enrollment\Application\Port\CalendarFacts;
use DateTimeImmutable;

/**
 * The school years and the calendar lock, through AcademicCalendar's public
 * API (A-06, ADR 0007).
 */
final readonly class AcademicCalendarFacts implements CalendarFacts
{
    public function __construct(
        private SchoolYearLookup $years,
        private CalendarLock $calendarLock,
        private ListSchoolYearsHandler $list,
    ) {}

    public function statusOf(int $schoolYearId): ?string
    {
        return $this->years->statusOf($schoolYearId);
    }

    public function startOf(int $schoolYearId): ?DateTimeImmutable
    {
        return $this->years->startOf($schoolYearId);
    }

    public function activeYear(): ?int
    {
        return $this->years->activeYear();
    }

    public function years(): array
    {
        return array_map(
            fn (SchoolYearView $year) => ['id' => $year->id, 'name' => $year->name, 'status' => $year->status],
            ($this->list)(),
        );
    }

    public function nextOf(int $schoolYearId): ?int
    {
        return $this->years->nextOf($schoolYearId);
    }

    public function lastTermClosed(int $schoolYearId): bool
    {
        return $this->years->lastTermClosed($schoolYearId);
    }

    public function lock(): void
    {
        $this->calendarLock->lockInstitutionCalendar();
    }
}
