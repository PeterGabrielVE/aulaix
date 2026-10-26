<?php

declare(strict_types=1);

namespace AulaX\Platform\Domain;

use InvalidArgumentException;

final readonly class OperatorId
{
    private function __construct(public int $value) {}

    public static function fromInt(int $value): self
    {
        if ($value <= 0) {
            throw new InvalidArgumentException("Invalid operator id: {$value}.");
        }

        return new self($value);
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
