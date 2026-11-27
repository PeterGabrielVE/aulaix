<?php

use AulaX\Evaluation\Domain\ActivityInput;
use AulaX\Evaluation\Domain\EvaluationPlan;
use AulaX\Evaluation\Domain\PlanOwner;
use AulaX\Evaluation\Domain\TermDates;

/**
 * SPEC-016 (ADR 0013): a plan belongs to a section or to a group of pending
 * subjects, never both; the rules of the plan are the same for either.
 */
it('is a section or a group of pending subjects', function () {
    $section = PlanOwner::section(10);
    $group = PlanOwner::pendingGroup(3);

    expect($section->sectionId)->toBe(10)
        ->and($section->pendingGroupId)->toBeNull()
        ->and($section->isSection())->toBeTrue()
        ->and($group->pendingGroupId)->toBe(3)
        ->and($group->sectionId)->toBeNull()
        ->and($group->isSection())->toBeFalse()
        ->and($section->equals(PlanOwner::section(10)))->toBeTrue()
        ->and($section->equals($group))->toBeFalse()
        ->and(fn () => PlanOwner::section(0))->toThrow(InvalidArgumentException::class);
});

it('keeps the owner of a plan, a section by its id as before', function () {
    $dates = new TermDates(new DateTimeImmutable('2026-09-16'), new DateTimeImmutable('2026-12-15'));
    $inputs = [new ActivityInput(null, 'Prueba', '2026-10-05', '100')];

    $ofSection = EvaluationPlan::draft(2, 10, 4, 1, $inputs, $dates);
    $ofGroup = EvaluationPlan::draft(2, PlanOwner::pendingGroup(3), 4, 1, $inputs, $dates);

    expect($ofSection->owner()->equals(PlanOwner::section(10)))->toBeTrue()
        ->and($ofSection->sectionId())->toBe(10)
        ->and($ofGroup->owner()->equals(PlanOwner::pendingGroup(3)))->toBeTrue()
        ->and($ofGroup->sectionId())->toBeNull();
});
