<?php

use AulaX\Audit\Application\FieldChange;

it('keeps the field with its previous and new value', function () {
    $change = FieldChange::of('grade', 14, 16);

    expect([$change->field, $change->from, $change->to])->toBe(['grade', 14, 16]);
});

it('accepts null and lists of scalars as values', function () {
    $change = FieldChange::of('roles', null, ['Docente', 'Representante']);

    expect($change->to)->toBe(['Docente', 'Representante']);
});

it('refuses to record a sensitive field, whatever its value', function (string $field) {
    expect(fn () => FieldChange::of($field, null, null))->toThrow(LogicException::class, $field);
})->with(['password', 'password_hash', 'remember_token', 'invitation_token', 'apiSecret', 'PasswordConfirmation']);

it('refuses values that can not be stored as plain JSON', function () {
    expect(fn () => FieldChange::of('grade', null, new stdClass))->toThrow(LogicException::class);
});
