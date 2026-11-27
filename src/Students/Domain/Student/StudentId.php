<?php

declare(strict_types=1);

namespace AulaX\Students\Domain\Student;

use InvalidArgumentException;

final readonly class StudentId
{
    private function __construct(public int $value) {}

    public static function fromInt(int $value): self
    {
        if ($value <= 0) {
            throw new InvalidArgumentException("Invalid student id: {$value}.");
        }

        return new self($value);
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
