<?php

declare(strict_types=1);

namespace AulaX\Enrollment\Domain;

use InvalidArgumentException;

final readonly class EnrollmentId
{
    private function __construct(public int $value) {}

    public static function fromInt(int $value): self
    {
        if ($value <= 0) {
            throw new InvalidArgumentException("Invalid enrollment id: {$value}.");
        }

        return new self($value);
    }
}
