<?php

use AulaX\Catalogs\Application\EducationLevels;

/**
 * SPEC-006 INS-R03.1: the levels of the catalog, for other modules.
 */
it('lists the catalog levels with their names', function () {
    expect((new EducationLevels)->all())->toBe([
        ['code' => 'inicial', 'name' => 'Educación Inicial'],
        ['code' => 'primaria', 'name' => 'Educación Primaria'],
        ['code' => 'media_general', 'name' => 'Educación Media General'],
    ]);
});

it('tells whether a code is a catalog level', function () {
    expect((new EducationLevels)->exists('primaria'))->toBeTrue()
        ->and((new EducationLevels)->exists('universitaria'))->toBeFalse();
});
