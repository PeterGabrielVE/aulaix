<?php

namespace Tests\Fixtures\Evaluation;

use AulaX\Evaluation\Application\Port\CalendarFacts;
use AulaX\Evaluation\Domain\TermDates;
use DateTimeImmutable;

/**
 * The terms of school year 2 (2026-2027), as a test sets them, with the
 * rules of CAL-R05.
 */
final class FakeCalendarFacts implements CalendarFacts
{
    /** @var array<int, array{start: string, end: string, status: string}> */
    public array $terms = [
        1 => ['start' => '2026-09-16', 'end' => '2026-12-15', 'status' => 'open'],
        2 => ['start' => '2027-01-07', 'end' => '2027-03-31', 'status' => 'pending'],
        3 => ['start' => '2027-04-12', 'end' => '2027-07-15', 'status' => 'pending'],
    ];

    public bool $yearClosed = false;

    public ?int $activeYear = 2;

    public function activeYear(): ?int
    {
        return $this->activeYear;
    }

    public function termDates(int $schoolYearId, int $term): ?TermDates
    {
        $dates = $schoolYearId === 2 ? ($this->terms[$term] ?? null) : null;

        return $dates === null ? null : new TermDates(new DateTimeImmutable($dates['start']), new DateTimeImmutable($dates['end']));
    }

    public function termStatus(int $schoolYearId, int $term): ?string
    {
        return $schoolYearId === 2 ? ($this->terms[$term]['status'] ?? null) : null;
    }

    public function gradesEditable(int $schoolYearId, int $term, bool $canEditClosedTerms): bool
    {
        return match ($this->termStatus($schoolYearId, $term)) {
            'open' => ! $this->yearClosed,
            'closed' => $canEditClosedTerms && ! $this->yearClosed,
            default => false,
        };
    }
}
