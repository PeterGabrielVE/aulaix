<?php

/**
 * SPEC-014 PLT-R06.6, V3: the environment's list of consolidation operators
 * is gone; the panel's operators replace it, with no period where both work.
 */
test('the old list of consolidation operators no longer exists', function () {
    expect(config()->has('identity.consolidation_operators'))->toBeFalse()
        ->and(file_get_contents(base_path('.env.example')))->not->toContain('IDENTITY_CONSOLIDATION_OPERATORS')
        ->and(class_exists('AulaX\IdentityAccess\Infrastructure\ConfigConsolidationOperators'))->toBeFalse();
});
