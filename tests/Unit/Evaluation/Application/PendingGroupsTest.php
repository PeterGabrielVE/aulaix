<?php

use AulaX\Evaluation\Application\PendingGroups\AssignPendingTeacherCommand;
use AulaX\Evaluation\Application\PendingGroups\AssignPendingTeacherHandler;
use AulaX\Evaluation\Application\PendingGroups\PendingGroupEntry;
use AulaX\Evaluation\Application\PendingGroups\PendingGroupsQuery;
use AulaX\Evaluation\Application\PendingGroups\PendingMembers;
use AulaX\Evaluation\Application\Port\RosterStudentFact;
use AulaX\Evaluation\Domain\Activity;
use AulaX\Evaluation\Domain\ActivityGrades;
use AulaX\Evaluation\Domain\EvaluationPlan;
use AulaX\Evaluation\Domain\GradeMark;
use AulaX\Evaluation\Domain\OwedPending;
use AulaX\Evaluation\Domain\PendingGroup;
use AulaX\Evaluation\Domain\PendingGroupNotFound;
use AulaX\Evaluation\Domain\PlanOwner;
use AulaX\Evaluation\Domain\Revision;
use AulaX\Evaluation\Domain\TeacherNotEligible;
use AulaX\Evaluation\Domain\Weight;
use AulaX\Shared\Domain\AuthorizationDenied;
use Tests\Fixtures\Evaluation\EvaluationScene;

/**
 * SPEC-016 RVP-R03.1 – R03.6, R07.1, NF01: the groups of pending subjects of
 * the year, with their members derived from the results of the years
 * before, and the teacher a manager assigns to each.
 */
beforeEach(function () {
    $this->scene = new EvaluationScene;
    $this->scene->permissions->assignPendingTeachers = true;
    $this->scene->teaching->activeTeachers = [9 => 'Ana Rivas', 8 => 'Luis Mora'];
    $this->scene->enrollments->students = [
        1 => new RosterStudentFact(1, 'Pérez, Ana', 'V1'),
        2 => new RosterStudentFact(2, 'Álvarez, Luis', 'V2'),
        3 => new RosterStudentFact(3, 'Mora, Eva', 'V3'),
        4 => new RosterStudentFact(4, 'Rojas, Paz', 'V4'),
        5 => new RosterStudentFact(5, 'Díaz, Leo', 'V5'),
    ];
    // Matemática (4) and Física (5) of 2.º año, failed in a year before (7).
    $this->scene->enrollments->owed = [
        new OwedPending(1, 4, 'media_2', 7),
        new OwedPending(2, 4, 'media_2', 7),
        new OwedPending(3, 5, 'media_2', 7),
        new OwedPending(4, 4, 'media_2', 7),
        new OwedPending(5, 4, 'media_2', 7),
    ];

    // In school year 1, Paz (4) approved Matemática of 2.º año in its group; Leo (5) failed it, revision included.
    $this->scene->calendar->closedYears = [1];
    $old = PendingGroup::open(1, 4, 'media_2');
    $this->scene->store->saveGroup($old);
    foreach ([1 => '2025-10-05', 2 => '2026-02-02', 3 => '2026-05-04'] as $term => $date) {
        $this->scene->store->save(EvaluationPlan::reconstitute(null, 1, PlanOwner::pendingGroup($old->id()), 4, $term, [new Activity(null, 'Prueba', new DateTimeImmutable($date), Weight::of('100'))]));
        $activity = $this->scene->store->planOfGroup($old->id(), $term)->activities()[0];
        $this->scene->store->saveGrades(ActivityGrades::of($activity->id, PlanOwner::pendingGroup($old->id()), $activity->date, [4 => GradeMark::fromInput('15'), 5 => GradeMark::fromInput('7')]));
    }
    $this->scene->store->saveRevision(Revision::of(null, 1, 4, 5, PlanOwner::pendingGroup($old->id()), GradeMark::notPresented()));
});

function pendingGroups(): array
{
    $scene = test()->scene;

    return (new PendingGroupsQuery(pendingMembers(), $scene->store, $scene->teaching, $scene->permissions))(2);
}

function pendingMembers(): PendingMembers
{
    $scene = test()->scene;

    return new PendingMembers($scene->store, $scene->store, $scene->store, $scene->store, $scene->enrollments, $scene->calendar);
}

function assignPendingTeacher(?int $account, int $subject = 4, string $grade = 'media_2'): void
{
    $scene = test()->scene;
    (new AssignPendingTeacherHandler(pendingMembers(), $scene->store, $scene->teaching, $scene->permissions, $scene->audit, $scene->transactions))(
        new AssignPendingTeacherCommand(2, $subject, $grade, $account),
    );
}

it('gathers the pending subjects of the year by subject and grade of origin, without who approved them in a year before', function () {
    expect(array_map(fn (PendingGroupEntry $group) => [$group->subject, $group->originGrade, $group->teacher, array_column($group->members, 'fullName')], pendingGroups()))->toBe([
        ['Matemática', 'media_2', null, ['Álvarez, Luis', 'Díaz, Leo', 'Pérez, Ana']],
        ['Física', 'media_2', null, ['Mora, Eva']],
    ]);
});

it('a manager assigns a teacher to a group, recorded with who was before', function () {
    assignPendingTeacher(9);

    expect(pendingGroups()[0]->teacher)->toBe('Ana Rivas')
        ->and($this->scene->audit->types())->toBe(['evaluation.pending_group.teacher_assigned'])
        ->and($this->scene->audit->changesOf(0))->toBe(['teacher' => ['from' => null, 'to' => 9]]);

    assignPendingTeacher(8);
    assignPendingTeacher(8);

    expect($this->scene->audit->changesOf(1))->toBe(['teacher' => ['from' => 9, 'to' => 8]])
        ->and($this->scene->audit->types())->toHaveCount(2);
});

it('assigns only an active member with the role Docente, to a group someone owes', function () {
    expect(fn () => assignPendingTeacher(7))->toThrow(TeacherNotEligible::class, 'Solo se asigna a un miembro activo con el rol Docente.')
        ->and(fn () => assignPendingTeacher(9, subject: 6))->toThrow(PendingGroupNotFound::class);
});

it('only a manager of the structure assigns the teachers and sees the groups', function () {
    $this->scene->permissions->assignPendingTeachers = false;

    expect(fn () => assignPendingTeacher(9))->toThrow(AuthorizationDenied::class)
        ->and(fn () => pendingGroups())->toThrow(AuthorizationDenied::class);
});
