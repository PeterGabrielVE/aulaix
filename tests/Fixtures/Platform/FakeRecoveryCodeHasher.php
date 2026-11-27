<?php

namespace Tests\Fixtures\Platform;

use AulaX\Platform\Domain\RecoveryCodeHasher;

/**
 * Deterministic keyed hasher for unit tests (SPEC-017 RSP-NF02).
 */
final readonly class FakeRecoveryCodeHasher implements RecoveryCodeHasher
{
    public function __construct(private string $secret = 'test-recovery-code-key') {}

    public function hash(string $normalizedCode): string
    {
        return hash_hmac('sha256', hash('sha256', $normalizedCode), $this->secret);
    }
}
