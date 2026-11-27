<?php

use AulaX\Audit\Application\FieldChange;
use AulaX\Evaluation\Application\EvaluationAudit;
use AulaX\Evaluation\Domain\Activity;
use AulaX\Evaluation\Domain\ActivityId;
use AulaX\Evaluation\Domain\EvaluationPlan;
use AulaX\Evaluation\Domain\GradeChange;
use AulaX\Evaluation\Domain\Weight;

/**
 * SPEC-011 EVA-R06.3, R10.1: the plan before and after, and the grades that
 * changed, flagged when the term was closed.
 */
function auditChanges(array $changes): array
{
    return array_column(array_map(fn (FieldChange $change) => [$change->field, [$change->from, $change->to]], $changes), 1, 0);
}

it('records a plan with its activities before and after', function () {
    $before = [new Activity(ActivityId::fromInt(1), 'Prueba 1', new DateTimeImmutable('2026-10-01'), Weight::of('100'))];
    $plan = EvaluationPlan::reconstitute(5, 2, 10, 4, 1, [
        new Activity(ActivityId::fromInt(1), 'Prueba 1', new DateTimeImmutable('2026-10-01'), Weight::of('60')),
        new Activity(ActivityId::fromInt(2), 'Taller', new DateTimeImmutable('2026-11-03'), Weight::of('40')),
    ]);
    $event = EvaluationAudit::planChanged($plan, $before);

    expect($event->type)->toBe('evaluation.plan.changed')
        ->and($event->subject->type)->toBe('evaluation_plan')
        ->and($event->subject->id)->toBe('5')
        ->and(auditChanges($event->changes)['activities'])->toBe([
            ['Prueba 1 · 01/10/2026 · 100 %'],
            ['Prueba 1 · 01/10/2026 · 60 %', 'Taller · 03/11/2026 · 40 %'],
        ]);
});

it('records the grades that changed, and whether the term was closed', function () {
    $event = EvaluationAudit::gradesSaved(7, [new GradeChange(3, null, '15,5'), new GradeChange(4, '12', 'NP')], closedTerm: true);

    expect($event->type)->toBe('evaluation.activity.grades_saved')
        ->and($event->subject->id)->toBe('7')
        ->and(auditChanges($event->changes))->toBe([
            'student_3' => [null, '15,5'],
            'student_4' => ['12', 'NP'],
            'closed_term' => [null, true],
        ]);
});
