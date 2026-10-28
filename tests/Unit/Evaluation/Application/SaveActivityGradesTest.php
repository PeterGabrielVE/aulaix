<?php

use AulaX\Evaluation\Application\EvaluationNotFound;
use AulaX\Evaluation\Application\SaveActivityGrades\SaveActivityGradesHandler;
use AulaX\Evaluation\Application\SavePlan\SavePlanCommand;
use AulaX\Evaluation\Application\SavePlan\SavePlanHandler;
use AulaX\Evaluation\Domain\ActivityInput;
use AulaX\Evaluation\Domain\GradeNotValid;
use AulaX\Evaluation\Domain\NotEditable;
use AulaX\Shared\Domain\AuthorizationDenied;
use Tests\Fixtures\Evaluation\EvaluationScene;

/**
 * SPEC-011 EVA-R01.8, R02.1 – R02.8, R06.1 – R06.4, R07.1, R10.1: the grades
 * of an activity, saved for the whole section at once.
 */
beforeEach(function () {
    $this->scene = new EvaluationScene;
    $this->scene->teaching->teaching[] = [9, 10, 4];
    $scene = $this->scene;
    (new SavePlanHandler($scene->store, $scene->store, $scene->teaching, $scene->calendar, $scene->permissions, $scene->audit, $scene->transactions))(
        new SavePlanCommand(2, 10, 4, 1, [new ActivityInput(null, 'Prueba 1', '2026-10-05', '60'), new ActivityInput(null, 'Taller', '2026-11-03', '40')], false),
    );
    $this->scene->enrollments->enroll(1, 10, name: 'Pérez, Ana');
    $this->scene->enrollments->enroll(2, 10, name: 'Álvarez, Luis');
    // Arrived after the first activity.
    $this->scene->enrollments->enroll(3, 10, from: '2026-10-15', name: 'Mora, Eva');
    // Left for another section.
    $this->scene->enrollments->enroll(4, 10, until: '2026-10-10', name: 'Rojas, Paz');
    $this->scene->audit->recorded = [];
});

function saveGrades(array $grades, int $activity = 1): void
{
    $scene = test()->scene;
    (new SaveActivityGradesHandler($scene->store, $scene->store, $scene->teaching, $scene->calendar, $scene->enrollments, $scene->permissions, $scene->clock, $scene->audit, $scene->transactions))($activity, $grades);
}

it('saves the grades of an activity for the section, NP and none included, recorded with what changed', function () {
    saveGrades([1 => '15,5', 2 => 'NP']);
    $grades = $this->scene->store->grades[1];

    expect($grades->markOf(1)?->value())->toBe(1550)
        ->and($grades->markOf(2)?->isNotPresented())->toBeTrue()
        ->and($this->scene->audit->types())->toBe(['evaluation.activity.grades_saved'])
        ->and($this->scene->audit->changesOf(0))->toBe(['student_1' => ['from' => null, 'to' => '15,5'], 'student_2' => ['from' => null, 'to' => 'NP']])
        ->and($this->scene->audit->recorded[0]['inTransaction'])->toBeTrue();

    saveGrades([1 => '15,5', 2 => '']);

    expect($this->scene->store->grades[1]->markOf(2))->toBeNull()
        ->and($this->scene->audit->changesOf(1))->toBe(['student_2' => ['from' => 'NP', 'to' => null]]);
});

it('records nothing when nothing changed', function () {
    saveGrades([1 => '15']);
    saveGrades([1 => '15,00']);

    expect($this->scene->audit->types())->toBe(['evaluation.activity.grades_saved']);
});

it('refuses a grade out of the scale, saying of whom', function () {
    try {
        saveGrades([1 => '21']);
        $this->fail('The grade was taken.');
    } catch (GradeNotValid $violation) {
        expect($violation->field())->toBe('grades.1')
            ->and($violation->getMessage())->toBe('La nota debe estar entre 1 y 20, con hasta dos decimales.');
    }
});

it('refuses whoever is no longer in the section, and a grade for an activity before the student arrived', function () {
    expect(fn () => saveGrades([4 => '10']))->toThrow(GradeNotValid::class, 'El estudiante no tiene inscripción vigente en la sección.')
        ->and(fn () => saveGrades([3 => 'NP']))->toThrow(GradeNotValid::class, 'La actividad es anterior a la incorporación del estudiante: no aplica.');

    saveGrades([3 => '18'], activity: 2);

    expect($this->scene->store->grades[2]->markOf(3)?->value())->toBe(1800);
});

it('refuses whoever does not teach the subject, and an activity it does not have', function () {
    $this->scene->permissions->accountId = 8;

    expect(fn () => saveGrades([1 => '10']))->toThrow(AuthorizationDenied::class, 'No dictas esa materia en esa sección.')
        ->and(fn () => saveGrades([1 => '10'], activity: 99))->toThrow(EvaluationNotFound::class);
});

it('a closed term takes grades only with the special permission, flagged in the audit log; a closed year never', function () {
    $this->scene->calendar->terms[1]['status'] = 'closed';

    expect(fn () => saveGrades([1 => '10']))->toThrow(NotEditable::class, 'El lapso está cerrado: sus notas no cambian.');

    $this->scene->permissions->editClosedTerms = true;
    saveGrades([1 => '10']);

    expect($this->scene->audit->changesOf(0)['closed_term'])->toBe(['from' => null, 'to' => true]);

    $this->scene->calendar->yearClosed = true;

    expect(fn () => saveGrades([1 => '11']))->toThrow(NotEditable::class, 'El año escolar está cerrado: sus notas no cambian.');
});
