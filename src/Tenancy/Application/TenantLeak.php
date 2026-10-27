<?php

declare(strict_types=1);

namespace AulaX\Tenancy\Application;

use LogicException;

/**
 * An institution was left active where another one, or none, was expected
 * (SPEC-015 RLT-R02.3). Thrown inside the transaction, which is rolled
 * back: nothing written with the wrong institution is kept.
 */
final class TenantLeak extends LogicException
{
    public static function expected(?int $institutionId, string $actual): self
    {
        $expected = $institutionId === null ? 'none' : "#{$institutionId}";

        return new self("Expected institution {$expected} to be active, found '{$actual}'.");
    }
}
