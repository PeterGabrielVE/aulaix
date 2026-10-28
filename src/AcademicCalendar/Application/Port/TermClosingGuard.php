<?php

declare(strict_types=1);

namespace AulaX\AcademicCalendar\Application\Port;

/**
 * An extension point for the modules that build on the calendar (ADR 0012):
 * SPEC-011 does not let a term close with grades pending, unless a manager
 * closes it by exception with a justification, which fills them first
 * (EVA-R12.2 – R12.4). Asked under the calendar lock, inside the closing's
 * transaction. Until a module registers one, nothing blocks.
 */
interface TermClosingGuard
{
    /**
     * The container tag a module puts its guard under.
     */
    public const string TAG = 'academic_calendar.term_closing_guards';

    /**
     * How many grades, literals or reports of the term are still missing.
     */
    public function pendingGrades(int $schoolYearId, int $term): int;

    /**
     * Fills what is pending (NP, incomplete) and records why.
     */
    public function fillPending(int $schoolYearId, int $term, string $justification): void;
}
