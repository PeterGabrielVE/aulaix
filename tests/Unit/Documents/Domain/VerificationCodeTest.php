<?php

use AulaX\Documents\Domain\VerificationCode;

/**
 * SPEC-012 DOC-R07.1, NF02 (ADR 0015): 128-bit non-sequential verification
 * codes, Crockford Base32 formatting, normalization and SHA-256 hashing.
 */
it('generates non-sequential 128-bit opaque codes and normalizes input for hashing', function () {
    $first = VerificationCode::generate();
    $second = VerificationCode::generate();

    expect($first->normalized)->toHaveLength(26)
        ->and($second->normalized)->toHaveLength(26)
        ->and($first->normalized)->not->toBe($second->normalized)
        ->and($first->hash())->toHaveLength(64)
        ->and($first->hint())->toBe('…'.substr($first->normalized, -6))
        ->and(VerificationCode::tryNormalize($first->formatted()))->toBe($first->normalized)
        ->and(VerificationCode::tryNormalize('  '.strtolower($first->formatted()).'  '))->toBe($first->normalized)
        ->and(VerificationCode::hashRaw($first->formatted()))->toBe($first->hash())
        ->and(VerificationCode::tryNormalize('demasiado-corto'))->toBeNull()
        ->and(VerificationCode::hashRaw('OOOO-IIII-LLLL-UUUU-0000-000000'))->toBeNull();
});
