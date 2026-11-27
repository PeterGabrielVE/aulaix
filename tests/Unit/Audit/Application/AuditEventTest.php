<?php

use AulaX\Audit\Application\AuditEvent;
use AulaX\Audit\Application\AuditSubject;
use AulaX\Audit\Application\FieldChange;

it('describes what changed on which object', function () {
    $event = AuditEvent::of(
        'evaluation.grade.changed',
        AuditSubject::of('grade', 42),
        [FieldChange::of('grade', 14, 16)],
    );

    expect($event->type)->toBe('evaluation.grade.changed')
        ->and($event->subject->type)->toBe('grade')
        ->and($event->subject->id)->toBe('42')
        ->and($event->changes)->toHaveCount(1);
});

it('may carry no values, for events that only record that something happened', function () {
    $event = AuditEvent::of('identity.password.changed', AuditSubject::of('account', 7));

    expect($event->changes)->toBe([]);
});

it('requires an event type shaped module.object.action', function (string $type) {
    expect(fn () => AuditEvent::of($type, AuditSubject::of('grade', 1)))->toThrow(LogicException::class);
})->with(['grade changed', 'Evaluation.Grade.Changed', 'evaluation.changed', '', 'evaluation..changed']);

it('requires a subject type and id', function (string $type, string $id) {
    expect(fn () => AuditSubject::of($type, $id))->toThrow(LogicException::class);
})->with([['', '1'], ['grade', '']]);
