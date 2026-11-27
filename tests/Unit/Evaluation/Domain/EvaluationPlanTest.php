<?php

use AulaX\Evaluation\Domain\Activity;
use AulaX\Evaluation\Domain\ActivityId;
use AulaX\Evaluation\Domain\ActivityInput;
use AulaX\Evaluation\Domain\EvaluationPlan;
use AulaX\Evaluation\Domain\PlanNotValid;
use AulaX\Evaluation\Domain\RemovalNeedsConfirmation;
use AulaX\Evaluation\Domain\TermDates;
use AulaX\Evaluation\Domain\Weight;

/**
 * SPEC-011 EVA-R01.1 – R01.3, R01.5, R01.7: the activities of a subject in a
 * section and a term, with weights that add up to 100 %.
 */
function firstTerm(): TermDates
{
    return new TermDates(new DateTimeImmutable('2026-09-16'), new DateTimeImmutable('2026-12-15'));
}

function activity(string $name, string $weight, string $date = '2026-10-01', ?int $id = null): ActivityInput
{
    return new ActivityInput($id, $name, $date, $weight);
}

function draftPlan(ActivityInput ...$activities): EvaluationPlan
{
    return EvaluationPlan::draft(2, 10, 4, 1, $activities, firstTerm());
}

it('keeps the activities of a subject in a section and a term, with their weight', function () {
    $plan = draftPlan(activity('Prueba 1', '30'), activity('Taller', '20,5', '2026-11-03'), activity('Prueba 2', '49,5', '2026-12-10'));

    expect($plan->schoolYearId())->toBe(2)
        ->and($plan->sectionId())->toBe(10)
        ->and($plan->subjectId())->toBe(4)
        ->and($plan->term())->toBe(1)
        ->and(array_map(fn ($activity) => [$activity->name, $activity->date->format('Y-m-d'), $activity->weight->hundredths], $plan->activities()))->toBe([
            ['Prueba 1', '2026-10-01', 3000],
            ['Taller', '2026-11-03', 2050],
            ['Prueba 2', '2026-12-10', 4950],
        ]);
});

it('takes a single activity with the whole 100 %', function () {
    expect(draftPlan(activity('Proyecto final', '100'))->activities())->toHaveCount(1);
});

it('refuses weights that do not add up to 100 %, saying how much they do', function () {
    expect(fn () => draftPlan(activity('Prueba', '30'), activity('Taller', '60')))
        ->toThrow(PlanNotValid::class, 'Los porcentajes suman 90 %: deben sumar 100 %.')
        ->and(fn () => draftPlan(activity('Prueba', '50,25'), activity('Taller', '50')))
        ->toThrow(PlanNotValid::class, 'Los porcentajes suman 100,25 %: deben sumar 100 %.')
        ->and(fn () => draftPlan())
        ->toThrow(PlanNotValid::class, 'Los porcentajes suman 0 %: deben sumar 100 %.');
});

it('refuses an activity with no name, a wrong weight or a date out of the term, saying which', function () {
    expect(fn () => draftPlan(activity('Prueba', '50'), activity('  ', '50')))
        ->toThrow(PlanNotValid::class, 'La actividad 2 necesita un nombre.')
        ->and(fn () => draftPlan(activity('Prueba', '0'), activity('Taller', '100')))
        ->toThrow(PlanNotValid::class, 'El porcentaje de la actividad 1 debe ser mayor que 0 y de hasta 100, con hasta dos decimales.')
        ->and(fn () => draftPlan(activity('Prueba', '100', '2026-12-16')))
        ->toThrow(PlanNotValid::class, 'La fecha de la actividad 1 debe estar dentro del lapso (16/09/2026 – 15/12/2026).')
        ->and(fn () => draftPlan(activity('Prueba', '100', 'mañana')))
        ->toThrow(PlanNotValid::class, 'La fecha de la actividad 1 debe estar dentro del lapso (16/09/2026 – 15/12/2026).');
});

it('replacing the activities keeps the ones sent with their id, and removes the rest', function () {
    $plan = EvaluationPlan::reconstitute(null, 2, 10, 4, 1, [
        planActivity(1, 'Prueba 1', 5000),
        planActivity(2, 'Prueba 2', 5000),
    ]);

    $removed = $plan->replace([activity('Prueba 1 (ajustada)', '60', id: 1), activity('Exposición', '40')], firstTerm(), withGrades: [], confirmed: false);

    expect($removed)->toBe([2])
        ->and(array_map(fn ($activity) => [$activity->id?->value, $activity->name], $plan->activities()))->toBe([[1, 'Prueba 1 (ajustada)'], [null, 'Exposición']]);
});

it('removing activities with grades asks to confirm, and then removes them', function () {
    $plan = EvaluationPlan::reconstitute(null, 2, 10, 4, 1, [planActivity(1, 'Prueba 1', 5000), planActivity(2, 'Prueba 2', 5000)]);

    expect(fn () => $plan->replace([activity('Prueba 1', '100', id: 1)], firstTerm(), withGrades: [2], confirmed: false))
        ->toThrow(RemovalNeedsConfirmation::class, 'Quitarás 1 actividad con notas: confirma para quitarla con sus notas.')
        ->and($plan->activities())->toHaveCount(2)
        ->and($plan->replace([activity('Prueba 1', '100', id: 1)], firstTerm(), withGrades: [2], confirmed: true))->toBe([2]);
});

function planActivity(int $id, string $name, int $weight): Activity
{
    return new Activity(ActivityId::fromInt($id), $name, new DateTimeImmutable('2026-10-01'), Weight::of((string) ($weight / 100)));
}
