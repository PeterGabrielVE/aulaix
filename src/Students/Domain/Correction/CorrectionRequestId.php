<?php

declare(strict_types=1);

namespace AulaX\Students\Domain\Correction;

use InvalidArgumentException;

final readonly class CorrectionRequestId
{
    private function __construct(public int $value) {}

    public static function fromInt(int $value): self
    {
        if ($value <= 0) {
            throw new InvalidArgumentException("Invalid correction request id: {$value}.");
        }

        return new self($value);
    }
}
