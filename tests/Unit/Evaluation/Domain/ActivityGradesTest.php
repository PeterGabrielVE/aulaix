<?php

use AulaX\Evaluation\Domain\ActivityGrades;
use AulaX\Evaluation\Domain\ActivityId;
use AulaX\Evaluation\Domain\EnrollmentPeriod;
use AulaX\Evaluation\Domain\GradeMark;
use AulaX\Evaluation\Domain\GradeNotValid;

/**
 * SPEC-011 EVA-R02.1 – R02.5, R02.8: the grades of one activity, for the
 * students it applies to.
 */
function gradesOfActivity(array $existing = []): ActivityGrades
{
    return ActivityGrades::of(ActivityId::fromInt(7), 10, new DateTimeImmutable('2026-10-10'), $existing);
}

function periodsFor(): array
{
    return [
        // In the section all along.
        1 => [new EnrollmentPeriod(10, 'media_3', new DateTimeImmutable('2026-09-16'), null)],
        2 => [new EnrollmentPeriod(10, 'media_3', new DateTimeImmutable('2026-09-16'), null)],
        // Arrived after the activity.
        3 => [new EnrollmentPeriod(10, 'media_3', new DateTimeImmutable('2026-11-01'), null)],
        // Left the section.
        4 => [new EnrollmentPeriod(10, 'media_3', new DateTimeImmutable('2026-09-16'), new DateTimeImmutable('2026-10-20'))],
    ];
}

it('records the grades of the students of the section, NP and none included, and tells what changed', function () {
    $grades = gradesOfActivity([2 => GradeMark::fromInput('12')]);

    $changes = $grades->record([1 => GradeMark::fromInput('15,5'), 2 => GradeMark::notPresented()], periodsFor(), new DateTimeImmutable('2026-10-25'));

    expect($grades->markOf(1)?->value())->toBe(1550)
        ->and($grades->markOf(2)?->isNotPresented())->toBeTrue()
        ->and(array_map(fn ($change) => [$change->studentId, $change->before, $change->after], $changes))->toBe([[1, null, '15,5'], [2, '12', 'NP']]);

    $changes = $grades->record([2 => null, 1 => GradeMark::fromInput('15,5')], periodsFor(), new DateTimeImmutable('2026-10-25'));

    expect($grades->markOf(2))->toBeNull()
        ->and(array_map(fn ($change) => [$change->studentId, $change->before, $change->after], $changes))->toBe([[2, 'NP', null]]);
});

it('refuses a grade for whoever is not in the section, or for an activity before it arrived', function () {
    $grades = gradesOfActivity();

    expect(fn () => $grades->record([4 => GradeMark::fromInput('10')], periodsFor(), new DateTimeImmutable('2026-10-25')))
        ->toThrow(GradeNotValid::class, 'El estudiante no tiene inscripción vigente en la sección.')
        ->and(fn () => $grades->record([9 => GradeMark::fromInput('10')], periodsFor(), new DateTimeImmutable('2026-10-25')))
        ->toThrow(GradeNotValid::class, 'El estudiante no tiene inscripción vigente en la sección.')
        ->and(fn () => $grades->record([3 => GradeMark::notPresented()], periodsFor(), new DateTimeImmutable('2026-11-05')))
        ->toThrow(GradeNotValid::class, 'La actividad es anterior a la incorporación del estudiante: no aplica.');

    try {
        $grades->record([3 => GradeMark::fromInput('10')], periodsFor(), new DateTimeImmutable('2026-11-05'));
    } catch (GradeNotValid $violation) {
        expect($violation->field())->toBe('grades.3');
    }
});

it('records nothing when one grade is refused', function () {
    $grades = gradesOfActivity();

    expect(fn () => $grades->record([1 => GradeMark::fromInput('10'), 4 => GradeMark::fromInput('10')], periodsFor(), new DateTimeImmutable('2026-10-25')))
        ->toThrow(GradeNotValid::class)
        ->and($grades->markOf(1))->toBeNull();
});
