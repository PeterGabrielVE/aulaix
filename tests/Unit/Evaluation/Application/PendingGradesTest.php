<?php

use AulaX\Evaluation\Application\Hooks\PendingGradesGuard;
use AulaX\Evaluation\Application\Marks\SaveLiteralCommand;
use AulaX\Evaluation\Application\Marks\SaveLiteralHandler;
use AulaX\Evaluation\Application\Pending\PendingGrades;
use AulaX\Evaluation\Application\Pending\PendingGradesQuery;
use AulaX\Evaluation\Application\Pending\PendingGradesRow;
use AulaX\Evaluation\Application\SaveActivityGrades\SaveActivityGradesHandler;
use AulaX\Evaluation\Application\SavePlan\SavePlanCommand;
use AulaX\Evaluation\Application\SavePlan\SavePlanHandler;
use AulaX\Evaluation\Domain\ActivityInput;
use AulaX\Evaluation\Domain\MarkKind;
use AulaX\Shared\Domain\AuthorizationDenied;
use Tests\Fixtures\Evaluation\EvaluationScene;

/**
 * SPEC-011 EVA-R12.1 – R12.5: what is still missing in a term, by teacher,
 * section, subject and activity (or literal or report), leaving out what
 * does not apply and whoever left; and, closing by exception, NP and
 * incomplete in its place, with the justification in the audit log.
 */
beforeEach(function () {
    $scene = $this->scene = new EvaluationScene;
    $scene->teaching->curricula['2:media_3'] = [4, 5];
    $scene->teaching->teaching = [[9, 10, 4]];
    $scene->teaching->guiding = [[9, 20]];
    $scene->teaching->staff = ['teachers' => [10 => [4 => 'Ana Rivas']], 'guides' => [20 => 'Ana Rivas', 30 => 'Luis Mora']];
    $scene->enrollments->enroll(1, 10, name: 'Pérez, Ana');
    $scene->enrollments->enroll(2, 10, name: 'Álvarez, Luis');
    // Arrived after the Prueba: NA there.
    $scene->enrollments->enroll(3, 10, from: '2026-10-15', name: 'Mora, Eva');
    // Left: not counted.
    $scene->enrollments->enroll(7, 10, until: '2026-10-10', name: 'Rojas, Paz');
    $scene->enrollments->enroll(4, 20, 'primaria_1', name: 'Díaz, Leo');
    $scene->enrollments->enroll(5, 20, 'primaria_1', name: 'Rivas, Mar');
    $scene->enrollments->enroll(6, 30, 'preescolar_2', name: 'Soto, Mía');
    (new SavePlanHandler($scene->store, $scene->store, $scene->teaching, $scene->calendar, $scene->permissions, $scene->audit, $scene->transactions))(
        new SavePlanCommand(2, 10, 4, 1, [new ActivityInput(null, 'Prueba', '2026-10-05', '50'), new ActivityInput(null, 'Taller', '2026-10-20', '50')], false),
    );
    (new SaveActivityGradesHandler($scene->store, $scene->store, $scene->teaching, $scene->calendar, $scene->enrollments, $scene->permissions, $scene->clock, $scene->audit, $scene->transactions))(1, [1 => '15']);
    (new SaveLiteralHandler($scene->store, $scene->teaching, $scene->calendar, $scene->enrollments, $scene->permissions, $scene->clock, $scene->audit, $scene->transactions))(
        new SaveLiteralCommand(2, 20, 5, 1, 'B', null),
    );
    $scene->audit->recorded = [];
});

function pendingGrades(): PendingGrades
{
    $scene = test()->scene;

    return new PendingGrades($scene->store, $scene->store, $scene->store, $scene->teaching, $scene->enrollments);
}

function pendingGuard(): PendingGradesGuard
{
    $scene = test()->scene;

    return new PendingGradesGuard(pendingGrades(), $scene->store, $scene->store, $scene->enrollments, $scene->clock, $scene->audit);
}

it('lists what is missing by teacher, section, subject and activity, leaving out NA and whoever left', function () {
    $scene = $this->scene;
    $scene->permissions->seesAll = true;
    $view = (new PendingGradesQuery(pendingGrades(), $scene->teaching, $scene->permissions))(2, 1);

    expect($view->total)->toBe(9)
        ->and(array_map(fn (PendingGradesRow $row) => [$row->teacher, $row->section, $row->subject, $row->item, $row->students], $view->rows))->toBe([
            ['Ana Rivas', '3.er año A', 'Matemática', 'Prueba', 1],
            ['Ana Rivas', '3.er año A', 'Matemática', 'Taller', 3],
            [null, '3.er año A', 'Física', 'Sin plan de evaluación', 3],
            ['Ana Rivas', '1.er grado A', null, 'Literal', 1],
            ['Luis Mora', '2.º nivel A', null, 'Informe', 1],
        ]);
});

it('counts every missing grade, literal and report for the closing of the term', function () {
    // 1 + 3 + 3 (no plan) + 1 literal + 1 report.
    expect(pendingGuard()->pendingGrades(2, 1))->toBe(9);
});

it('fills NP and incomplete closing by exception, recording the justification', function () {
    pendingGuard()->fillPending(2, 1, 'Reposo médico del docente.');

    expect($this->scene->store->grades[1]->markOf(2)?->isNotPresented())->toBeTrue()
        ->and($this->scene->store->grades[1]->markOf(3))->toBeNull()
        ->and($this->scene->store->grades[2]->markOf(3)?->isNotPresented())->toBeTrue()
        ->and($this->scene->store->grades[1]->markOf(7))->toBeNull()
        ->and($this->scene->store->marks[4]->markOf('1', MarkKind::Literal)?->incomplete)->toBeTrue()
        ->and($this->scene->store->marks[6]->markOf('1', MarkKind::Report)?->incomplete)->toBeTrue()
        // What has no plan has nothing to fill.
        ->and(pendingGuard()->pendingGrades(2, 1))->toBe(3)
        ->and($this->scene->audit->types())->toContain('evaluation.term.closed_by_exception')
        ->and($this->scene->audit->changesOf(count($this->scene->audit->recorded) - 1))->toBe([
            'term' => ['from' => null, 'to' => 1],
            'justification' => ['from' => null, 'to' => 'Reposo médico del docente.'],
            'filled' => ['from' => null, 'to' => 6],
        ]);
});

it('shows what is pending only to whoever manages students', function () {
    $scene = $this->scene;

    expect(fn () => (new PendingGradesQuery(pendingGrades(), $scene->teaching, $scene->permissions))(2, 1))->toThrow(AuthorizationDenied::class);
});
