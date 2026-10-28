<?php

use AulaX\Evaluation\Application\EvaluationNotFound;
use AulaX\Evaluation\Application\SavePlan\SavePlanCommand;
use AulaX\Evaluation\Application\SavePlan\SavePlanHandler;
use AulaX\Evaluation\Domain\ActivityInput;
use AulaX\Evaluation\Domain\GradeMark;
use AulaX\Evaluation\Domain\NotEditable;
use AulaX\Evaluation\Domain\PlanNotValid;
use AulaX\Evaluation\Domain\RemovalNeedsConfirmation;
use AulaX\Shared\Domain\AuthorizationDenied;
use AulaX\Shared\Domain\BusinessRuleViolation;
use Tests\Fixtures\Evaluation\EvaluationScene;

/**
 * SPEC-011 EVA-R01.1 – R01.8, R06.1, R06.4, R07.1, R10.1: the plan of a
 * subject in a section and a term, by whoever teaches it.
 */
beforeEach(function () {
    $this->scene = new EvaluationScene;
    $this->scene->teaching->teaching[] = [9, 10, 4];
});

function savePlan(array $activities, int $subject = 4, int $section = 10, int $term = 1, bool $confirm = false): void
{
    $scene = test()->scene;
    (new SavePlanHandler($scene->store, $scene->store, $scene->teaching, $scene->calendar, $scene->permissions, $scene->audit, $scene->transactions))(
        new SavePlanCommand(2, $section, $subject, $term, $activities, $confirm),
    );
}

function planActivityInput(string $name, string $weight, ?int $id = null, string $date = '2026-10-01'): ActivityInput
{
    return new ActivityInput($id, $name, $date, $weight);
}

it('saves the plan of whoever teaches the subject, recorded inside the transaction', function () {
    savePlan([planActivityInput('Prueba 1', '60'), planActivityInput('Taller', '40')]);

    expect(array_map(fn ($activity) => [$activity->id?->value, $activity->name], $this->scene->store->activitiesOf(10, 4, 1)))->toBe([[1, 'Prueba 1'], [2, 'Taller']])
        ->and($this->scene->audit->types())->toBe(['evaluation.plan.changed'])
        ->and($this->scene->audit->recorded[0]['inTransaction'])->toBeTrue();
});

it('refuses whoever does not teach it, a subject out of the curriculum, and a section of another level', function () {
    $this->scene->teaching->teaching[] = [9, 10, 8];
    $this->scene->teaching->teaching[] = [9, 20, 4];

    expect(fn () => savePlan([planActivityInput('Prueba', '100')], subject: 5))->toThrow(AuthorizationDenied::class, 'No dictas esa materia en esa sección.')
        ->and(fn () => savePlan([planActivityInput('Prueba', '100')], subject: 8))->toThrow(PlanNotValid::class, 'La materia no está en el pensum del grado de la sección.')
        ->and(fn () => savePlan([planActivityInput('Prueba', '100')], section: 20))->toThrow(BusinessRuleViolation::class, 'El plan de actividades es de Media General.')
        ->and(fn () => savePlan([planActivityInput('Prueba', '100')], section: 99))->toThrow(EvaluationNotFound::class);
});

it('refuses a term that is not open, unless closed and with the special permission', function () {
    expect(fn () => savePlan([planActivityInput('Prueba', '100', date: '2027-02-01')], term: 2))->toThrow(NotEditable::class, 'El lapso aún no se abre.');

    $this->scene->calendar->terms[1]['status'] = 'closed';

    expect(fn () => savePlan([planActivityInput('Prueba', '100')]))->toThrow(NotEditable::class, 'El lapso está cerrado: sus notas no cambian.');

    $this->scene->permissions->editClosedTerms = true;
    savePlan([planActivityInput('Prueba', '100')]);

    expect($this->scene->store->activitiesOf(10, 4, 1))->toHaveCount(1);
});

it('removing an activity with grades asks to confirm, and then removes its grades too', function () {
    savePlan([planActivityInput('Prueba 1', '60'), planActivityInput('Taller', '40')]);
    $grades = $this->scene->store->forActivity($this->scene->store->activitiesOf(10, 4, 1)[1], 10);
    $this->scene->enrollments->enroll(3, 10);
    $grades->record([3 => GradeMark::fromInput('15')], $this->scene->enrollments->periods, new DateTimeImmutable('2026-10-20'));
    $this->scene->store->saveGrades($grades);

    expect(fn () => savePlan([planActivityInput('Prueba 1', '100', id: 1)]))->toThrow(RemovalNeedsConfirmation::class, 'Quitarás 1 actividad con notas');

    savePlan([planActivityInput('Prueba 1', '100', id: 1)], confirm: true);

    expect($this->scene->store->activitiesOf(10, 4, 1))->toHaveCount(1)
        ->and($this->scene->store->grades)->toBe([])
        ->and($this->scene->audit->types())->toBe(['evaluation.plan.changed', 'evaluation.plan.changed']);
});
