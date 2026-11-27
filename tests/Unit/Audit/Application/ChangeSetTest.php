<?php

use AulaX\Audit\Application\ChangeSet;
use AulaX\Audit\Application\FieldChange;

it('lists only the fields whose value changed', function () {
    $changes = ChangeSet::diff(
        ['grade' => 14, 'observation' => 'Bien', 'status' => 'draft'],
        ['grade' => 16, 'observation' => 'Bien', 'status' => 'published'],
    );

    expect($changes)->toEqual([
        FieldChange::of('grade', 14, 16),
        FieldChange::of('status', 'draft', 'published'),
    ]);
});

it('treats a field present on one side only as changed from or to null', function () {
    $changes = ChangeSet::diff(['observation' => 'Bien'], ['grade' => 16]);

    expect($changes)->toEqual([
        FieldChange::of('grade', null, 16),
        FieldChange::of('observation', 'Bien', null),
    ]);
});

it('finds nothing when the values are the same', function () {
    expect(ChangeSet::diff(['grade' => 14], ['grade' => 14]))->toBe([]);
});

it('tells a number from its text, so a changed type is a change', function () {
    expect(ChangeSet::diff(['grade' => '14'], ['grade' => 14]))->toHaveCount(1);
});
