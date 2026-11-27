<?php

declare(strict_types=1);

namespace AulaX\Shared\Application;

use AulaX\Shared\Domain\AuthorizationDenied;
use LogicException;

/**
 * Proof that a query runs inside the platform's bounded read of one
 * institution (SPEC-014 V2, ADR 0010). The aggregate queries the platform
 * uses have no institution permissions to check; they ask for this instead.
 *
 * Only Platform's TenantScopedReader opens it (an architecture test forbids
 * anyone else), for the length of one read.
 */
final class PlatformReadScope
{
    private static ?int $institutionId = null;

    /**
     * @template T
     *
     * @param  callable(): T  $read
     * @return T
     */
    public static function within(int $institutionId, callable $read): mixed
    {
        if (self::$institutionId !== null) {
            throw new LogicException('Platform reads do not nest.');
        }

        self::$institutionId = $institutionId;

        try {
            return $read();
        } finally {
            self::$institutionId = null;
        }
    }

    /**
     * @throws AuthorizationDenied
     */
    public static function ensureActive(): void
    {
        if (self::$institutionId === null) {
            throw new AuthorizationDenied('Esta consulta solo responde dentro de una lectura de la plataforma.');
        }
    }

    public static function institutionId(): ?int
    {
        return self::$institutionId;
    }
}
