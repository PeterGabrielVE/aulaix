<?php

declare(strict_types=1);

namespace AulaX\Evaluation\Domain;

use InvalidArgumentException;

final readonly class ActivityId
{
    private function __construct(public int $value) {}

    public static function fromInt(int $value): self
    {
        if ($value <= 0) {
            throw new InvalidArgumentException("Invalid activity id: {$value}.");
        }

        return new self($value);
    }
}
