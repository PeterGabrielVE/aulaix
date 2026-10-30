<?php

use AulaX\Evaluation\Application\Hooks\PendingGradesGuard;
use AulaX\Evaluation\Application\Marks\SaveLiteralCommand;
use AulaX\Evaluation\Application\Marks\SaveLiteralHandler;
use AulaX\Evaluation\Application\Pending\PendingGrades;
use AulaX\Evaluation\Application\Pending\PendingGradesQuery;
use AulaX\Evaluation\Application\Pending\PendingGradesRow;
use AulaX\Evaluation\Application\PendingGroups\PendingMembers;
use AulaX\Evaluation\Application\SaveActivityGrades\SaveActivityGradesHandler;
use AulaX\Evaluation\Application\SavePlan\SavePlanCommand;
use AulaX\Evaluation\Application\SavePlan\SavePlanHandler;
use AulaX\Evaluation\Domain\Activity;
use AulaX\Evaluation\Domain\ActivityGrades;
use AulaX\Evaluation\Domain\ActivityInput;
use AulaX\Evaluation\Domain\EvaluationPlan;
use AulaX\Evaluation\Domain\GradeMark;
use AulaX\Evaluation\Domain\MarkKind;
use AulaX\Evaluation\Domain\OwedPending;
use AulaX\Evaluation\Domain\PendingGroup;
use AulaX\Evaluation\Domain\PlanOwner;
use AulaX\Evaluation\Domain\Weight;
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

function pendingGradesWithGroups(): PendingGrades
{
    $scene = test()->scene;
    $members = new PendingMembers($scene->store, $scene->store, $scene->store, $scene->store, $scene->enrollments, $scene->calendar);

    return new PendingGrades($scene->store, $scene->store, $scene->store, $scene->teaching, $scene->enrollments, $members);
}

/**
 * SPEC-016 RVP-R04.7: the groups of pending subjects count with the rest,
 * and closing by exception fills them with NP.
 */
function withPendingGroup(object $test): int
{
    $test->scene->teaching->activeTeachers = [9 => 'Ana Rivas'];
    // Ana (1) and Luis (2) owe Matemática of 2.º año, in its group; Eva (3) owes Física, still without one.
    $test->scene->enrollments->owed = [new OwedPending(1, 4, 'media_2', 7), new OwedPending(2, 4, 'media_2', 7), new OwedPending(3, 5, 'media_2', 7)];
    $group = PendingGroup::open(2, 4, 'media_2');
    $group->assignTeacher(9);
    $test->scene->store->saveGroup($group);
    $test->scene->store->save(EvaluationPlan::reconstitute(null, 2, PlanOwner::pendingGroup((int) $group->id()), 4, 1, [new Activity(null, 'Prueba de pendiente', new DateTimeImmutable('2026-10-05'), Weight::of('100'))]));
    $activity = $test->scene->store->planOfGroup((int) $group->id(), 1)->activities()[0];
    $test->scene->store->saveGrades(ActivityGrades::of($activity->id, PlanOwner::pendingGroup((int) $group->id()), $activity->date, [1 => GradeMark::fromInput('15')]));

    return (int) $activity->id->value;
}

it('counts what the groups of pending subjects lack, with their teacher', function () {
    withPendingGroup($this);
    $this->scene->permissions->seesAll = true;
    $view = (new PendingGradesQuery(pendingGradesWithGroups(), $this->scene->teaching, $this->scene->permissions))(2, 1);

    expect($view->total)->toBe(11)
        ->and(array_map(fn (PendingGradesRow $row) => [$row->teacher, $row->section, $row->subject, $row->item, $row->students], array_slice($view->rows, -2)))->toBe([
            ['Ana Rivas', 'Pendientes de 2.º año', 'Matemática', 'Prueba de pendiente', 1],
            [null, 'Pendientes de 2.º año', 'Física', 'Sin plan de evaluación', 1],
        ]);
});

it('fills with NP the grades a group lacks, closing by exception', function () {
    $activity = withPendingGroup($this);
    $scene = $this->scene;

    (new PendingGradesGuard(pendingGradesWithGroups(), $scene->store, $scene->store, $scene->enrollments, $scene->clock, $scene->audit))->fillPending(2, 1, 'Cierre del lapso.');

    expect($this->scene->store->grades[$activity]->markOf(2)?->isNotPresented())->toBeTrue()
        ->and($this->scene->store->grades[$activity]->markOf(1)?->value())->toBe(1500);
});
