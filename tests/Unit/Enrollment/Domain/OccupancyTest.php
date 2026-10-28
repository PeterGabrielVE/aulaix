<?php

use AulaX\Enrollment\Domain\Level;
use AulaX\Enrollment\Domain\Occupancy;
use AulaX\Enrollment\Domain\OverCapacity;
use AulaX\Enrollment\Domain\Placement;

/**
 * SPEC-010 MAT-R02.1 – R02.3: the capacity of a section, gone over only
 * with a reason.
 */
it('admits up to the capacity, with no reason needed', function () {
    expect((new Occupancy(30, 29))->admit(null))->toBeNull()
        ->and((new Occupancy(30, 29))->admit('  '))->toBeNull()
        ->and((new Occupancy(30, 0))->isOver())->toBeFalse();
});

it('over the capacity asks for a reason, and keeps it', function () {
    expect(fn () => (new Occupancy(30, 30))->admit(null))
        ->toThrow(OverCapacity::class, 'La sección tiene 30 inscritos y cupo 30: indica el motivo para superarlo.');

    try {
        (new Occupancy(30, 31))->admit('');
    } catch (OverCapacity $violation) {
        expect($violation->field())->toBe('over_capacity_reason');
    }

    expect((new Occupancy(30, 30))->admit('  Hermanos en la misma sección  '))->toBe('Hermanos en la misma sección')
        ->and(fn () => (new Occupancy(30, 30))->admit(str_repeat('a', 501)))->toThrow(OverCapacity::class);
});

it('knows whether the section is over its capacity', function () {
    expect((new Occupancy(30, 30))->isOver())->toBeFalse()
        ->and((new Occupancy(30, 31))->isOver())->toBeTrue();
});

it('places in a section of a grade of its level', function () {
    $placement = Placement::of(7, 'media_2', 'media_general');

    expect($placement->sectionId)->toBe(7)
        ->and($placement->grade)->toBe('media_2')
        ->and($placement->level)->toBe(Level::Secondary)
        ->and(Level::from('inicial'))->toBe(Level::Initial)
        ->and(Level::from('primaria'))->toBe(Level::Primary);
});
