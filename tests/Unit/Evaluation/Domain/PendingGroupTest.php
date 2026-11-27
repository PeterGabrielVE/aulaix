<?php

use AulaX\Evaluation\Domain\OwedPending;
use AulaX\Evaluation\Domain\PendingGroup;

/**
 * SPEC-016 RVP-R03.1, R03.4: a group of pending subjects per year, subject
 * and grade of origin of Media General, with one teacher or none.
 */
it('is a subject of a grade of origin of Media General in a year', function () {
    $group = PendingGroup::open(2, 4, 'media_3');

    expect($group->schoolYearId())->toBe(2)
        ->and($group->subjectId())->toBe(4)
        ->and($group->originGrade())->toBe('media_3')
        ->and($group->teacherAccountId())->toBeNull()
        ->and(fn () => PendingGroup::open(2, 4, 'primaria_3'))->toThrow(InvalidArgumentException::class);
});

it('takes one teacher, changes it or leaves it without one, and tells who was before', function () {
    $group = PendingGroup::open(2, 4, 'media_3');

    expect($group->assignTeacher(9))->toBe([null, 9])
        ->and($group->assignTeacher(9))->toBeNull()
        ->and($group->assignTeacher(8))->toBe([9, 8])
        ->and($group->assignTeacher(null))->toBe([8, null])
        ->and($group->teacherAccountId())->toBeNull();
});

it('gathers the pending subjects it is for', function () {
    $owed = new OwedPending(1, 4, 'media_3', 1);

    expect($owed->belongsTo(PendingGroup::open(2, 4, 'media_3')))->toBeTrue()
        ->and($owed->belongsTo(PendingGroup::open(2, 4, 'media_2')))->toBeFalse()
        ->and($owed->belongsTo(PendingGroup::open(2, 5, 'media_3')))->toBeFalse();
});
