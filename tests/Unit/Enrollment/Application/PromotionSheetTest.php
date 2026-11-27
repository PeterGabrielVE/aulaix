<?php

use AulaX\Catalogs\Application\Subjects;
use AulaX\Enrollment\Application\EnrollStudent\EnrollStudentCommand;
use AulaX\Enrollment\Application\EnrollStudent\EnrollStudentHandler;
use AulaX\Enrollment\Application\Roster\PromotionSheet;
use AulaX\Enrollment\Application\Roster\PromotionSheetQuery;
use AulaX\Enrollment\Domain\EnrollmentId;
use AulaX\Enrollment\Domain\Outcome;
use AulaX\Enrollment\Domain\PromotionResult;
use AulaX\Shared\Domain\AuthorizationDenied;
use Tests\Fixtures\Enrollment\EnrollmentScene;

/**
 * SPEC-010 MAT-R06.10: the results of a section, who has one and who does
 * not, with what its level allows.
 */
beforeEach(function () {
    $this->scene = new EnrollmentScene;
    $this->scene->structure->section(40, 2, 'media_3', 'A');
    $this->scene->structure->curricula['2:media_3'] = [4, 5];
    $this->scene->students->add(6, '2012-03-10', name: 'Pérez, Ana');
    $this->scene->students->add(7, '2012-04-10', name: 'Álvarez, Luis');
    $scene = $this->scene;
    $enroll = fn (int $student) => (new EnrollStudentHandler($scene->enrollments, $scene->students, $scene->structure, $scene->calendar, $scene->ages, $scene->permissions, $scene->clock, $scene->audit, $scene->transactions))(
        new EnrollStudentCommand($student, 2, 40, '2026-09-16', null, null, 3),
    )->enrollmentId;
    $this->ana = $enroll(6);
    $enroll(7);
    $enrollment = $scene->enrollments->byId(EnrollmentId::fromInt($this->ana));
    $enrollment->recordResult(PromotionResult::of(Outcome::PromotedWithPending, [5]), [4, 5], new DateTimeImmutable('2027-07-10'), 3);
    $scene->enrollments->save($enrollment);
    $this->subjects = new class implements Subjects
    {
        public function ofLevel(string $level): array
        {
            return [['id' => 5, 'code' => 'FIS', 'name' => 'Física'], ['id' => 9, 'code' => 'ART', 'name' => 'Artes'], ['id' => 4, 'code' => 'MAT', 'name' => 'Matemática']];
        }

        public function levelsOf(array $subjectIds): array
        {
            return [];
        }
    };
});

function promotionSheet(): PromotionSheet
{
    $scene = test()->scene;

    return (new PromotionSheetQuery($scene->enrollments, $scene->students, $scene->structure, $scene->calendar, test()->subjects, $scene->permissions))(2, 40);
}

it('lists the students of the section with their result, and the subjects of its curriculum', function () {
    $sheet = promotionSheet();

    expect(array_map(fn ($row) => [$row->fullName, $row->outcome, $row->pendingSubjects], $sheet->students))->toBe([
        ['Álvarez, Luis', null, []],
        ['Pérez, Ana', 'promoted_with_pending', [5]],
    ])->and($sheet->outcomes)->toBe(['promoted', 'promoted_with_pending', 'repeats', 'lost_by_absence'])
        ->and($sheet->subjects)->toBe([['id' => 5, 'name' => 'Física'], ['id' => 4, 'name' => 'Matemática']])
        ->and($sheet->ready)->toBeFalse();

    $this->scene->calendar->years[2]['lastTermClosed'] = true;

    expect(promotionSheet()->ready)->toBeTrue();
});

it('is for whoever manages enrollments', function () {
    $this->scene->permissions->manage = false;

    expect(fn () => promotionSheet())->toThrow(AuthorizationDenied::class);
});
