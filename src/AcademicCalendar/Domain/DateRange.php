<?php

declare(strict_types=1);

namespace AulaX\AcademicCalendar\Domain;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

/**
 * A span of whole days, both ends included: the dates of a school year or of
 * a term. Callers check the order first and report it as a business rule;
 * an inverted range reaching here is a programming error.
 */
final readonly class DateRange
{
    private function __construct(
        public DateTimeImmutable $start,
        public DateTimeImmutable $end,
    ) {}

    public static function of(DateTimeImmutable $start, DateTimeImmutable $end): self
    {
        $start = self::day($start);
        $end = self::day($end);

        if ($end < $start) {
            throw new InvalidArgumentException('A date range can not end before it starts.');
        }

        return new self($start, $end);
    }

    /**
     * @param  string  $start  Y-m-d
     * @param  string  $end  Y-m-d
     */
    public static function fromStrings(string $start, string $end): self
    {
        return self::of(self::parse($start), self::parse($end));
    }

    public static function parse(string $date): DateTimeImmutable
    {
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date, new DateTimeZone('UTC'));

        if ($parsed === false || $parsed->format('Y-m-d') !== $date) {
            throw new InvalidArgumentException("Not a Y-m-d date: {$date}.");
        }

        return $parsed;
    }

    public function contains(self $other): bool
    {
        return $other->start >= $this->start && $other->end <= $this->end;
    }

    public function overlaps(self $other): bool
    {
        return $this->start <= $other->end && $other->start <= $this->end;
    }

    public function startsAfter(self $other): bool
    {
        return $this->start > $other->end;
    }

    public function equals(self $other): bool
    {
        return $this->start == $other->start && $this->end == $other->end;
    }

    private static function day(DateTimeImmutable $date): DateTimeImmutable
    {
        return new DateTimeImmutable($date->format('Y-m-d'), new DateTimeZone('UTC'));
    }
}
