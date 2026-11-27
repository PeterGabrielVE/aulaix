<?php

use AulaX\Platform\Domain\RecoveryCodes;
use AulaX\Platform\Infrastructure\KeyedRecoveryCodeHasher;

/**
 * SPEC-017 RSP-NF02: keyed HMAC-SHA256 fingerprint of recovery codes,
 * independent of APP_KEY and impossible to brute-force from a database dump alone.
 */
test('produces a 64-character keyed HMAC that differs from plain SHA-256 and changes with the secret', function () {
    $hasherA = new KeyedRecoveryCodeHasher('secret-key-a');
    $hasherB = new KeyedRecoveryCodeHasher('secret-key-b');
    $normalized = RecoveryCodes::normalize('K3F9Q-2X7MZ');
    $plainSha256 = hash('sha256', $normalized);

    $digestA = $hasherA->hash($normalized);
    $digestB = $hasherB->hash($normalized);

    expect(strlen($digestA))->toBe(64)
        ->and($digestA)->not->toBe($plainSha256)
        ->and($digestA)->not->toBe($digestB)
        ->and($digestA)->toBe(KeyedRecoveryCodeHasher::rehash($plainSha256, 'secret-key-a'));
});

test('refuses an empty secret', function () {
    expect(fn () => new KeyedRecoveryCodeHasher('   '))->toThrow(RuntimeException::class);
});
