<?php

declare(strict_types=1);

namespace AulaX\AcademicCalendar\Domain;

/**
 * A school year of an institution and its three terms (F1-13, F1-14). Owns
 * every rule of a single year: its dates, its life cycle (planned → active →
 * closed), the terms opening and closing in order with exactly one open
 * while the year is active, and which terms admit grade changes. Rules
 * across the institution's years live in SchoolYearSchedule.
 *
 * Nothing here depends on today's date: terms open and close only when a
 * manager says so (CAL-R04.3).
 */
final class SchoolYear
{
    /**
     * @param  array<int, Term>  $terms  Keyed by term number.
     */
    private function __construct(
        private ?SchoolYearId $id,
        private DateRange $dates,
        private SchoolYearStatus $status,
        private array $terms,
    ) {}

    /**
     * CAL-R01.1: a new year is planned, none of its terms open yet.
     */
    public static function plan(SchoolYearDates $dates): self
    {
        $terms = [];

        foreach (TermNumber::all() as $number) {
            $terms[$number->value] = new Term($number, $dates->term($number), TermStatus::Pending);
        }

        return new self(null, $dates->year, SchoolYearStatus::Planned, $terms);
    }

    /**
     * @param  list<Term>  $terms
     */
    public static function reconstitute(SchoolYearId $id, DateRange $dates, SchoolYearStatus $status, array $terms): self
    {
        $byNumber = [];

        foreach ($terms as $term) {
            $byNumber[$term->number->value] = $term;
        }

        ksort($byNumber);

        return new self($id, $dates, $status, $byNumber);
    }

    public function id(): ?SchoolYearId
    {
        return $this->id;
    }

    public function identify(SchoolYearId $id): void
    {
        $this->id ??= $id;
    }

    public function dates(): DateRange
    {
        return $this->dates;
    }

    public function status(): SchoolYearStatus
    {
        return $this->status;
    }

    /**
     * @return list<Term> Terms 1 to 3.
     */
    public function terms(): array
    {
        return array_values($this->terms);
    }

    public function term(TermNumber $number): Term
    {
        return $this->terms[$number->value];
    }

    /**
     * CAL-R01.7: «2026-2027», from the years it starts and ends in. Follows
     * the dates, so it is never stored (CAL-R03.4).
     */
    public function name(): string
    {
        return $this->dates->start->format('Y').'-'.$this->dates->end->format('Y');
    }

    /**
     * CAL-R02.2. Whether another year is active is SchoolYearSchedule's call.
     */
    public function activate(): void
    {
        if ($this->status !== SchoolYearStatus::Planned) {
            throw CalendarRuleViolation::onField('status', 'Solo se puede activar un año planificado.');
        }

        $this->status = SchoolYearStatus::Active;
        $this->setTerm(TermNumber::of(1), TermStatus::Open);
    }

    /**
     * CAL-R02.4 – R02.6.
     */
    public function close(bool $confirmed): void
    {
        $this->assertConfirmed($confirmed);

        if ($this->status !== SchoolYearStatus::Active) {
            throw CalendarRuleViolation::onField('status', 'Solo se puede cerrar el año activo.');
        }

        foreach ($this->terms as $term) {
            if ($term->status !== TermStatus::Closed) {
                throw CalendarRuleViolation::onField('status', 'Cierra los tres lapsos antes de cerrar el año.');
            }
        }

        $this->status = SchoolYearStatus::Closed;
    }

    /**
     * CAL-R09.2: only a year that never started.
     */
    public function canBeDeleted(): bool
    {
        return $this->status === SchoolYearStatus::Planned;
    }

    /**
     * CAL-R03.1 – R03.3: the year's dates and those of its terms not closed
     * yet. The new dates were already checked together (SchoolYearDates).
     */
    public function changeDates(SchoolYearDates $dates): void
    {
        if ($this->status === SchoolYearStatus::Closed) {
            throw CalendarRuleViolation::onField('status', 'Las fechas de un año cerrado no se pueden cambiar.');
        }

        foreach ($this->terms as $number => $term) {
            if ($term->status === TermStatus::Closed && ! $term->dates->equals($dates->term($term->number))) {
                throw CalendarRuleViolation::onField('terms.'.($number - 1).'.ends_on', 'Las fechas de un lapso cerrado no se pueden cambiar.');
            }
        }

        $this->dates = $dates->year;

        foreach ($this->terms as $number => $term) {
            $this->terms[$number] = $term->withDates($dates->term($term->number));
        }
    }

    /**
     * CAL-R04.2: closes the open term and opens the next one, if any.
     *
     * @return TermNumber The term closed.
     */
    public function closeTerm(bool $confirmed): TermNumber
    {
        $this->assertConfirmed($confirmed);
        $this->assertActive();

        $open = $this->openTerm() ?? throw CalendarRuleViolation::onField('term', 'No hay ningún lapso abierto.');

        $this->setTerm($open->number, TermStatus::Closed);

        if (($next = $open->number->next()) !== null) {
            $this->setTerm($next, TermStatus::Open);
        }

        return $open->number;
    }

    /**
     * CAL-R04.4, R04.5, R04.7: only the last closed term, with a reason. The
     * term open at the time goes back to not yet open, so exactly one stays
     * open (CAL-R04.1).
     */
    public function reopenTerm(TermNumber $number, string $reason): void
    {
        if (trim($reason) === '') {
            throw CalendarRuleViolation::onField('reason', 'Indica el motivo de la reapertura.');
        }

        $this->assertActive();

        if ($this->lastClosedTerm()?->number->value !== $number->value) {
            throw CalendarRuleViolation::onField('term', 'Solo se puede reabrir el último lapso cerrado.');
        }

        if (($open = $this->openTerm()) !== null) {
            $this->setTerm($open->number, TermStatus::Pending);
        }

        $this->setTerm($number, TermStatus::Open);
    }

    /**
     * CAL-R05.1 – R05.3: whether the grades of a term may change, for
     * someone who has (or not) the editar-notas-lapso-cerrado permission.
     */
    public function allowsGradeChanges(TermNumber $number, bool $hasClosedTermPermission): bool
    {
        return match (true) {
            $this->status === SchoolYearStatus::Closed => false,
            $this->term($number)->status === TermStatus::Open => true,
            default => $hasClosedTermPermission,
        };
    }

    private function openTerm(): ?Term
    {
        foreach ($this->terms as $term) {
            if ($term->status === TermStatus::Open) {
                return $term;
            }
        }

        return null;
    }

    private function lastClosedTerm(): ?Term
    {
        $last = null;

        foreach ($this->terms as $term) {
            if ($term->status === TermStatus::Closed) {
                $last = $term;
            }
        }

        return $last;
    }

    private function setTerm(TermNumber $number, TermStatus $status): void
    {
        $this->terms[$number->value] = $this->terms[$number->value]->withStatus($status);
    }

    private function assertActive(): void
    {
        if ($this->status !== SchoolYearStatus::Active) {
            throw CalendarRuleViolation::onField('status', 'Los lapsos solo se abren, cierran o reabren en el año activo.');
        }
    }

    private function assertConfirmed(bool $confirmed): void
    {
        if (! $confirmed) {
            throw CalendarRuleViolation::confirmationMissing();
        }
    }
}
