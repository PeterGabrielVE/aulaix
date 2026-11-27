<?php

use AulaX\Documents\Domain\StudentPublicSummary;

/**
 * SPEC-012 DOC-R07.2, R07.3: public QR summary exposes only initials and last
 * four digits of the ID, never full names or complete identification numbers.
 */
it('derives student initials and last four digits without exposing full name or ID', function () {
    $summary = StudentPublicSummary::fromStudent('Ana María', 'Pérez Gómez', 'V-30123456');

    expect($summary->initials)->toBe('A. M. P. G.')
        ->and($summary->idLast4)->toBe('****3456')
        ->and($summary->initials)->not->toContain('Ana')
        ->and($summary->idLast4)->not->toContain('3012');

    $schoolId = StudentPublicSummary::fromStudent('José', 'Ñáñez', '11820456789');
    expect($schoolId->initials)->toBe('J. Ñ.')
        ->and($schoolId->idLast4)->toBe('****6789');

    $shortId = StudentPublicSummary::fromStudent('Luz', 'Ríos', '123');
    expect($shortId->idLast4)->toBe('—');
});
