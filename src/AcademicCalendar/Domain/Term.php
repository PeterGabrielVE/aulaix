<?php

declare(strict_types=1);

namespace AulaX\AcademicCalendar\Domain;

/**
 * One of the three terms of a school year, as its year holds it. Only the
 * SchoolYear aggregate creates or changes terms.
 */
final readonly class Term
{
    public function __construct(
        public TermNumber $number,
        public DateRange $dates,
        public TermStatus $status,
    ) {}

    public function withStatus(TermStatus $status): self
    {
        return new self($this->number, $this->dates, $status);
    }

    public function withDates(DateRange $dates): self
    {
        return new self($this->number, $dates, $this->status);
    }
}
