<?php

declare(strict_types=1);

namespace AulaX\Platform\Infrastructure;

use AulaX\Platform\Domain\RecoveryCodeHasher;
use RuntimeException;

/**
 * Keyed HMAC-SHA256 recovery code fingerprint (SPEC-017 RSP-NF02):
 * HMAC(SHA-256(normalized_code), PLATFORM_RECOVERY_CODE_KEY).
 *
 * Computing the HMAC over the legacy SHA-256 hash lets the data migration
 * upgrade existing rows in place without knowing the plaintext codes.
 */
final readonly class KeyedRecoveryCodeHasher implements RecoveryCodeHasher
{
    public function __construct(
        #[\SensitiveParameter] private string $secret,
    ) {
        if (trim($this->secret) === '') {
            throw new RuntimeException('PLATFORM_RECOVERY_CODE_KEY must not be empty.');
        }
    }

    public function hash(string $normalizedCode): string
    {
        return self::rehash(hash('sha256', $normalizedCode), $this->secret);
    }

    public static function rehash(string $sha256Hash, #[\SensitiveParameter] string $secret): string
    {
        return hash_hmac('sha256', $sha256Hash, $secret);
    }
}
