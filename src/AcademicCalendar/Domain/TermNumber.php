<?php

declare(strict_types=1);

namespace AulaX\AcademicCalendar\Domain;

use InvalidArgumentException;

/**
 * Which of the three terms (lapsos) of a school year (F1-13).
 */
final readonly class TermNumber
{
    public const COUNT = 3;

    private function __construct(public int $value) {}

    public static function of(int $value): self
    {
        if ($value < 1 || $value > self::COUNT) {
            throw new InvalidArgumentException("A school year has terms 1 to 3, not {$value}.");
        }

        return new self($value);
    }

    /**
     * @return list<self>
     */
    public static function all(): array
    {
        return array_map(fn (int $value) => new self($value), range(1, self::COUNT));
    }

    public function next(): ?self
    {
        return $this->isLast() ? null : new self($this->value + 1);
    }

    public function isLast(): bool
    {
        return $this->value === self::COUNT;
    }
}
