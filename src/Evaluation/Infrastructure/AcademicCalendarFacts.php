<?php

declare(strict_types=1);

namespace AulaX\Evaluation\Infrastructure;

use AulaX\AcademicCalendar\Application\ListSchoolYears\ListSchoolYearsHandler;
use AulaX\AcademicCalendar\Application\ListSchoolYears\SchoolYearView;
use AulaX\AcademicCalendar\Application\ListSchoolYears\TermView;
use AulaX\AcademicCalendar\Application\SchoolYearLookup;
use AulaX\AcademicCalendar\Application\TermEditability\TermEditabilityQuery;
use AulaX\Evaluation\Application\Port\CalendarFacts;
use AulaX\Evaluation\Domain\TermDates;
use DateTimeImmutable;

/**
 * The terms of a school year and whether their grades may change, through
 * AcademicCalendar's public API (A-06, CAL-R05).
 */
final readonly class AcademicCalendarFacts implements CalendarFacts
{
    public function __construct(
        private ListSchoolYearsHandler $years,
        private TermEditabilityQuery $editability,
        private SchoolYearLookup $lookup,
    ) {}

    public function activeYear(): ?int
    {
        return $this->lookup->activeYear();
    }

    public function termDates(int $schoolYearId, int $term): ?TermDates
    {
        $found = $this->term($schoolYearId, $term);

        return $found === null ? null : new TermDates(new DateTimeImmutable($found->startsOn), new DateTimeImmutable($found->endsOn));
    }

    public function termStatus(int $schoolYearId, int $term): ?string
    {
        return $this->term($schoolYearId, $term)?->status;
    }

    public function gradesEditable(int $schoolYearId, int $term, bool $canEditClosedTerms): bool
    {
        return $term >= 1 && $term <= 3 && ($this->editability)($schoolYearId, $term, $canEditClosedTerms);
    }

    private function term(int $schoolYearId, int $term): ?TermView
    {
        $years = array_values(array_filter(($this->years)(), fn (SchoolYearView $year) => $year->id === $schoolYearId));
        $terms = array_values(array_filter($years[0]->terms ?? [], fn (TermView $view) => $view->number === $term));

        return $terms[0] ?? null;
    }
}
