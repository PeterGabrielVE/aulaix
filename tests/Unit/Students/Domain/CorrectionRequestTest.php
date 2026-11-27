<?php

use AulaX\Shared\Domain\BusinessRuleViolation;
use AulaX\Students\Domain\Correction\CorrectionRequest;
use AulaX\Students\Domain\Correction\CorrectionStatus;
use AulaX\Students\Domain\Guardian\GuardianId;
use AulaX\Students\Domain\Student\StudentId;

/**
 * SPEC-009 EST-R08.4, R08.5: a guardian asks to correct a datum; a manager
 * marks it attended.
 */
it('is submitted pending, about a student or the guardian itself', function () {
    $aboutStudent = CorrectionRequest::submit(GuardianId::fromInt(1), StudentId::fromInt(3), ' La fecha de nacimiento es 05/03 ', new DateTimeImmutable('2026-10-22'));
    $aboutItself = CorrectionRequest::submit(GuardianId::fromInt(1), null, 'Cambié de teléfono', new DateTimeImmutable('2026-10-22'));

    expect($aboutStudent->status())->toBe(CorrectionStatus::Pending)
        ->and($aboutStudent->text())->toBe('La fecha de nacimiento es 05/03')
        ->and($aboutStudent->studentId()->value)->toBe(3)
        ->and($aboutItself->studentId())->toBeNull();
});

it('needs a text of up to 1000 characters', function (string $text) {
    expect(fn () => CorrectionRequest::submit(GuardianId::fromInt(1), null, $text, new DateTimeImmutable('2026-10-22')))
        ->toThrow(BusinessRuleViolation::class, 'Escribe qué dato hay que corregir, en 1000 caracteres como máximo.');
})->with(['empty' => [' '], 'too long' => [str_repeat('a', 1001)]]);

it('is marked attended once', function () {
    $request = CorrectionRequest::submit(GuardianId::fromInt(1), null, 'Cambié de teléfono', new DateTimeImmutable('2026-10-22'));

    $request->resolve(new DateTimeImmutable('2026-10-23'));

    expect($request->status())->toBe(CorrectionStatus::Resolved)
        ->and($request->resolvedAt()->format('Y-m-d'))->toBe('2026-10-23')
        ->and(fn () => $request->resolve(new DateTimeImmutable('2026-10-24')))->toThrow(BusinessRuleViolation::class, 'La solicitud ya está atendida.');
});
