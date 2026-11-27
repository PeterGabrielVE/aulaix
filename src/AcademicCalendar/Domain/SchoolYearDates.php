<?php

declare(strict_types=1);

namespace AulaX\AcademicCalendar\Domain;

use AulaX\Shared\Domain\BusinessRuleViolations;
use DateTimeImmutable;

/**
 * The dates of a school year and of its three terms, valid together
 * (CAL-R01.2 – R01.5, R01.8): the year ends after it starts and lasts 11
 * months at most; each term lies inside the year, ends on or after it
 * starts, and starts after the previous one ends. Days between terms are
 * fine, and the terms need not cover the whole year. Every broken rule is
 * reported at once, on its field.
 */
final readonly class SchoolYearDates
{
    private const MAX_DURATION = '+11 months';

    /**
     * @param  list<DateRange>  $terms  Terms 1 to 3, in order.
     */
    private function __construct(
        public DateRange $year,
        public array $terms,
    ) {}

    /**
     * @param  list<array{0: DateTimeImmutable, 1: DateTimeImmutable}>  $terms
     *
     * @throws BusinessRuleViolations
     */
    public static function of(DateTimeImmutable $start, DateTimeImmutable $end, array $terms): self
    {
        $violations = [];

        if ($end <= $start) {
            $violations[] = CalendarRuleViolation::onField('ends_on', 'La fecha de fin del año debe ser posterior a la de inicio.');
        } elseif ($end > $start->modify(self::MAX_DURATION)) {
            $violations[] = CalendarRuleViolation::onField('ends_on', 'El año escolar no puede durar más de 11 meses.');
        }

        $year = $end > $start ? DateRange::of($start, $end) : null;
        $ranges = [];
        $previous = null;

        foreach (TermNumber::all() as $index => $number) {
            [$termStart, $termEnd] = $terms[$index] ?? throw new \InvalidArgumentException('A school year has exactly three terms.');
            $field = "terms.{$index}";

            if ($termEnd < $termStart) {
                $violations[] = CalendarRuleViolation::onField("{$field}.ends_on", "El lapso {$number->value} no puede terminar antes de empezar.");
                $previous = null;

                continue;
            }

            $range = DateRange::of($termStart, $termEnd);

            if ($year !== null && $range->start < $year->start) {
                $violations[] = CalendarRuleViolation::onField("{$field}.starts_on", "El lapso {$number->value} no puede empezar antes que el año.");
            }

            if ($year !== null && $range->end > $year->end) {
                $violations[] = CalendarRuleViolation::onField("{$field}.ends_on", "El lapso {$number->value} no puede terminar después que el año.");
            }

            if ($previous !== null && ! $range->startsAfter($previous)) {
                $violations[] = CalendarRuleViolation::onField("{$field}.starts_on", "El lapso {$number->value} debe empezar después de que termine el lapso ".($number->value - 1).'.');
            }

            $ranges[] = $range;
            $previous = $range;
        }

        BusinessRuleViolations::throwIfAny($violations);

        return new self(DateRange::of($start, $end), $ranges);
    }

    /**
     * @param  list<array{0: string, 1: string}>  $terms  Y-m-d pairs.
     *
     * @throws BusinessRuleViolations
     */
    public static function fromStrings(string $start, string $end, array $terms): self
    {
        return self::of(
            DateRange::parse($start),
            DateRange::parse($end),
            array_map(fn (array $term) => [DateRange::parse($term[0]), DateRange::parse($term[1])], $terms),
        );
    }

    public function term(TermNumber $number): DateRange
    {
        return $this->terms[$number->value - 1];
    }
}
