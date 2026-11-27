<?php

declare(strict_types=1);

namespace AulaX\AcademicCalendar\Domain;

use InvalidArgumentException;

final readonly class SchoolYearId
{
    private function __construct(public int $value) {}

    public static function fromInt(int $value): self
    {
        if ($value <= 0) {
            throw new InvalidArgumentException("Invalid school year id: {$value}.");
        }

        return new self($value);
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
