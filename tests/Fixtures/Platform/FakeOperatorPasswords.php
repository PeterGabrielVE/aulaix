<?php

namespace Tests\Fixtures\Platform;

use AulaX\Platform\Application\Port\OperatorPasswords;

/**
 * In-memory stand-in for the Platform port of the same role (SPEC-014 tests).
 */
final class FakeOperatorPasswords implements OperatorPasswords
{
    public int $checks = 0;

    public function hash(string $password): string
    {
        return "hash:{$password}";
    }

    public function check(string $password, ?string $hash): bool
    {
        $this->checks++;

        return $hash !== null && $hash === "hash:{$password}";
    }
}
