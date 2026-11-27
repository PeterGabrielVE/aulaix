<?php

declare(strict_types=1);

namespace AulaX\Platform\Domain;

/**
 * Computes the keyed fingerprint of a normalized recovery code (SPEC-017
 * RSP-NF02). Lives in Domain so RecoveryCodes stays free of framework
 * configuration (constitution A-02).
 */
interface RecoveryCodeHasher
{
    public function hash(string $normalizedCode): string;
}
