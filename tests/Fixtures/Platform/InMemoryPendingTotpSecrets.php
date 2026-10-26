<?php

namespace Tests\Fixtures\Platform;

use AulaX\Platform\Application\Port\PendingTotpSecrets;

/**
 * The enrolment secrets of one browser session, in memory.
 */
final class InMemoryPendingTotpSecrets implements PendingTotpSecrets
{
    /** @var array<string, string> */
    public array $secrets = [];

    public function put(string $tokenHash, string $secret): void
    {
        $this->secrets[$tokenHash] = $secret;
    }

    public function get(string $tokenHash): ?string
    {
        return $this->secrets[$tokenHash] ?? null;
    }

    public function forget(string $tokenHash): void
    {
        unset($this->secrets[$tokenHash]);
    }
}
