<?php

declare(strict_types=1);

namespace AulaX\Documents\Domain;

use InvalidArgumentException;

/**
 * Identifier of an issued document (C-04).
 */
final readonly class DocumentId
{
    private function __construct(public int $value)
    {
        if ($value <= 0) {
            throw new InvalidArgumentException("DocumentId must be positive, got {$value}.");
        }
    }

    public static function fromInt(int $value): self
    {
        return new self($value);
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
