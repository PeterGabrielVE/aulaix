<?php

use AulaX\Evaluation\Application\EvaluationNotFound;
use AulaX\Evaluation\Application\PendingGroups\PendingMembers;
use AulaX\Evaluation\Application\PendingGroups\SaveGroupGradesHandler;
use AulaX\Evaluation\Application\PendingGroups\SaveGroupPlanCommand;
use AulaX\Evaluation\Application\PendingGroups\SaveGroupPlanHandler;
use AulaX\Evaluation\Application\PendingGroups\SaveGroupRevisionHandler;
use AulaX\Evaluation\Application\SaveActivityGrades\SaveActivityGradesHandler;
use AulaX\Evaluation\Domain\ActivityInput;
use AulaX\Evaluation\Domain\GradeNotValid;
use AulaX\Evaluation\Domain\NotEditable;
use AulaX\Evaluation\Domain\OwedPending;
use AulaX\Evaluation\Domain\PendingGroup;
use AulaX\Evaluation\Domain\PendingGroupNotFound;
use AulaX\Evaluation\Domain\PlanNotValid;
use AulaX\Evaluation\Domain\RevisionNotAllowed;
use AulaX\Shared\Domain\AuthorizationDenied;
use Tests\Fixtures\Evaluation\EvaluationScene;

/**
 * SPEC-016 RVP-R04.1, R04.2, R04.4, R04.5, R05.1, R07.1: only the teacher
 * assigned to a group of pending subjects plans it and grades its members,
 * with the rules of SPEC-011; every activity applies to every member.
 */
beforeEach(function () {
    $this->scene = new EvaluationScene;
    // Ana (1) and Luis (2) owe Matemática of 2.º año; Eva (3) does not.
    $this->scene->enrollments->owed = [new OwedPending(1, 4, 'media_2', 7), new OwedPending(2, 4, 'media_2', 7)];
    $group = PendingGroup::open(2, 4, 'media_2');
    $group->assignTeacher(9);
    $this->scene->store->saveGroup($group);
    $this->group = (int) $group->id();
    $this->scene->audit->recorded = [];
});

function groupMembers(): PendingMembers
{
    $scene = test()->scene;

    return new PendingMembers($scene->store, $scene->store, $scene->store, $scene->store, $scene->enrollments, $scene->calendar);
}

function saveGroupPlan(array $activities, int $term = 1, ?int $group = null): void
{
    $scene = test()->scene;
    (new SaveGroupPlanHandler($scene->store, $scene->store, $scene->store, $scene->teaching, $scene->calendar, $scene->permissions, $scene->audit, $scene->transactions))(
        new SaveGroupPlanCommand($group ?? test()->group, $term, array_map(fn (array $activity) => new ActivityInput(null, ...$activity), $activities), false),
    );
}

function saveGroupGrades(int $activity, array $grades): void
{
    $scene = test()->scene;
    (new SaveGroupGradesHandler(groupMembers(), $scene->store, $scene->store, $scene->store, $scene->teaching, $scene->calendar, $scene->permissions, $scene->clock, $scene->audit, $scene->transactions))($activity, $grades);
}

function saveGroupRevisions(array $revisions): void
{
    $scene = test()->scene;
    (new SaveGroupRevisionHandler(groupMembers(), $scene->store, $scene->store, $scene->teaching, $scene->calendar, $scene->enrollments, $scene->permissions, $scene->audit, $scene->transactions))(test()->group, $revisions);
}

it('the assigned teacher plans the group with the rules of a plan, recorded', function () {
    saveGroupPlan([['Prueba', '2026-10-05', '100']]);

    expect($this->scene->store->planOfGroup($this->group, 1)?->activities()[0]->name)->toBe('Prueba')
        ->and($this->scene->audit->types())->toBe(['evaluation.plan.changed'])
        ->and(fn () => saveGroupPlan([['Prueba', '2026-10-05', '60']]))->toThrow(PlanNotValid::class);
});

it('the assigned teacher grades the members, every activity applying to each, and nobody else', function () {
    saveGroupPlan([['Prueba', '2026-09-20', '100']]);
    $activity = (int) $this->scene->store->planOfGroup($this->group, 1)->activities()[0]->id->value;

    saveGroupGrades($activity, [1 => '15', 2 => 'NP']);

    expect($this->scene->store->grades[$activity]->markOf(1)?->value())->toBe(1500)
        ->and($this->scene->audit->types())->toContain('evaluation.activity.grades_saved')
        ->and(fn () => saveGroupGrades($activity, [3 => '15']))->toThrow(GradeNotValid::class, 'El estudiante no está en el grupo de pendientes.');
});

it('only the teacher assigned to the group plans and grades it', function () {
    $this->scene->permissions->accountId = 8;

    expect(fn () => saveGroupPlan([['Prueba', '2026-10-05', '100']]))->toThrow(AuthorizationDenied::class, 'No tienes asignado ese grupo de pendientes.')
        ->and(fn () => saveGroupPlan([['Prueba', '2026-10-05', '100']], group: 99))->toThrow(PendingGroupNotFound::class);
});

it('keeps the rules of the closed term, the closed year and the term not opened', function () {
    expect(fn () => saveGroupPlan([['Prueba', '2027-02-01', '100']], term: 2))->toThrow(NotEditable::class, 'El lapso aún no se abre.');

    $this->scene->calendar->terms[1]['status'] = 'closed';

    expect(fn () => saveGroupPlan([['Prueba', '2026-10-05', '100']]))->toThrow(NotEditable::class, 'El lapso está cerrado: sus notas no cambian.');
});

it('the assigned teacher records the revision of a member who failed the pending subject', function () {
    foreach ([1 => '2026-10-05', 2 => '2027-02-01', 3 => '2027-05-03'] as $term => $date) {
        $this->scene->calendar->terms[$term]['status'] = 'open';
        saveGroupPlan([['Prueba', $date, '100']], term: $term);
        $activity = (int) $this->scene->store->planOfGroup($this->group, $term)->activities()[0]->id->value;
        $this->scene->clock->now = new DateTimeImmutable($date.' 10:00');
        saveGroupGrades($activity, [1 => '8', 2 => '15']);
    }

    expect(fn () => saveGroupRevisions([1 => '12']))->toThrow(RevisionNotAllowed::class, 'La revisión se registra con el tercer lapso cerrado.');

    $this->scene->calendar->terms = array_map(fn (array $term) => [...$term, 'status' => 'closed'], $this->scene->calendar->terms);
    saveGroupRevisions([1 => '12']);

    expect($this->scene->store->revisions["g{$this->group}:1"]->mark()?->value())->toBe(1200)
        ->and(fn () => saveGroupRevisions([2 => '12']))->toThrow(RevisionNotAllowed::class, 'La definitiva no está reprobada: no lleva revisión.');
});

it('the grades of a section refuse an activity of a group', function () {
    saveGroupPlan([['Prueba', '2026-09-20', '100']]);
    $activity = (int) $this->scene->store->planOfGroup($this->group, 1)->activities()[0]->id->value;
    $scene = $this->scene;

    expect(fn () => (new SaveActivityGradesHandler($scene->store, $scene->store, $scene->teaching, $scene->calendar, $scene->enrollments, $scene->permissions, $scene->clock, $scene->audit, $scene->transactions))($activity, [1 => '15']))
        ->toThrow(EvaluationNotFound::class);
});
