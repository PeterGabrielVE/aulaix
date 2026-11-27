<?php

use AulaX\Evaluation\Application\Port\TeachingFacts;
use AulaX\Evaluation\Domain\PendingGroup;
use AulaX\Evaluation\Infrastructure\Persistence\EloquentPendingGroupRepository;

require_once dirname(__DIR__, 3).'/Fixtures/Evaluation/evaluation_helpers.php';

/**
 * SPEC-016 RVP-R03.4, R03.5: the groups of pending subjects through the
 * query builder, in the open institution, and who may teach them.
 */
beforeEach(function () {
    $this->institution = tenant(['education_levels' => ['media_general']]);
    $this->year = enrollmentYear();
    $this->subject = evaluationSubject();
    $this->repository = new EloquentPendingGroupRepository;
});

it('opens a group, changes its teacher and finds it by year, subject and grade of origin', function () {
    $teacher = userWithRole($this->institution, 'Docente');
    $group = PendingGroup::open($this->year, $this->subject, 'media_2');
    $group->assignTeacher($teacher->id);
    $this->repository->saveGroup($group);

    $found = $this->repository->groupOf($this->year, $this->subject, 'media_2');

    expect($found?->id())->toBe($group->id())
        ->and($found->teacherAccountId())->toBe($teacher->id)
        ->and($this->repository->groupOf($this->year, $this->subject, 'media_3'))->toBeNull();

    $found->assignTeacher(null);
    $this->repository->saveGroup($found);

    expect($this->repository->groupById((int) $group->id())?->teacherAccountId())->toBeNull()
        ->and(array_map(fn (PendingGroup $group) => $group->id(), $this->repository->groupsOf($this->year)))->toBe([$group->id()])
        ->and($this->repository->groupsOfSubject($this->subject, 'media_2'))->toHaveCount(1);
});

it('names the active members with the role Docente, and nobody else', function () {
    $teacher = userWithRole($this->institution, 'Docente', ['name' => 'Ana Rivas']);
    userWithRole($this->institution, 'Director', ['name' => 'Luis Mora']);
    $this->actingAs(userWithRole($this->institution, 'Administrador'));

    expect(app(TeachingFacts::class)->activeTeachers())->toBe([$teacher->id => 'Ana Rivas']);
});
