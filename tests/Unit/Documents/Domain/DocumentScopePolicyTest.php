<?php

use AulaX\Documents\Domain\DocumentScopePolicy;

/**
 * SPEC-012 DOC-R08.3: Coordinators can act only within their coordinated
 * grades; full-scope issuers can access any grade of the institution.
 */
it('restricts Coordinator to coordinated grades while allowing full-scope issuers', function () {
    $policy = new DocumentScopePolicy;

    expect($policy->canAccessGrade(true, [], 'media_5'))->toBeTrue()
        ->and($policy->canAccessGrade(false, ['media_1', 'media_2'], 'media_1'))->toBeTrue()
        ->and($policy->canAccessGrade(false, ['media_1', 'media_2'], 'media_5'))->toBeFalse()
        ->and($policy->canAccessAnyGrade(false, ['media_1'], ['primaria_6', 'media_1']))->toBeTrue()
        ->and($policy->canAccessAnyGrade(false, ['media_1'], ['primaria_6', 'media_2']))->toBeFalse()
        ->and($policy->canAccessAnyGrade(true, [], []))->toBeTrue();
});
