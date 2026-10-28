<?php

declare(strict_types=1);

namespace AulaX\Evaluation\Application\Port;

use AulaX\Evaluation\Domain\TermDates;

/**
 * The school years and their terms, as AcademicCalendar tells them (A-06).
 */
interface CalendarFacts
{
    /**
     * Null for a year or term the institution does not have.
     */
    public function termDates(int $schoolYearId, int $term): ?TermDates;

    /**
     * pending, open or closed; null for a year or term it does not have.
     */
    public function termStatus(int $schoolYearId, int $term): ?string;

    /**
     * Whether grades of the term may change (CAL-R05): open, or closed for
     * whoever may edit closed terms; never once the year is closed.
     */
    public function gradesEditable(int $schoolYearId, int $term, bool $canEditClosedTerms): bool;

    public function activeYear(): ?int;
}
