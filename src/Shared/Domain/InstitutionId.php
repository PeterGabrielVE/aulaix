<?php

declare(strict_types=1);

namespace AulaX\Shared\Domain;

use InvalidArgumentException;

/**
 * The identity of an institution (tenant). In Shared because every module
 * scopes its data by it, and modules may not import each other's domain (A-06).
 */
final readonly class InstitutionId
{
    private function __construct(public int $value) {}

    public static function fromInt(int $value): self
    {
        if ($value <= 0) {
            throw new InvalidArgumentException("Invalid institution id: {$value}.");
        }

        return new self($value);
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
