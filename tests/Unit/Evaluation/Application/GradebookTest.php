<?php

use AulaX\Evaluation\Application\EvaluationNotFound;
use AulaX\Evaluation\Application\Gradebook\GradebookQuery;
use AulaX\Evaluation\Application\Gradebook\GradebookView;
use AulaX\Evaluation\Application\Port\SectionInfo;
use AulaX\Evaluation\Application\SaveActivityGrades\SaveActivityGradesHandler;
use AulaX\Evaluation\Application\SavePlan\SavePlanCommand;
use AulaX\Evaluation\Application\SavePlan\SavePlanHandler;
use AulaX\Evaluation\Domain\ActivityInput;
use Tests\Fixtures\Evaluation\EvaluationScene;

/**
 * SPEC-011 EVA-R02.7, R03.1 – R03.7, R07.3 – R07.6: the sheet of a subject
 * in a section and a term, with the term and final grades, for whoever may
 * see it.
 */
beforeEach(function () {
    $this->scene = new EvaluationScene;
    $this->scene->teaching->sections[11] = new SectionInfo(11, 2, 'media_3', '3.er año', 'media_general', 'B');
    $this->scene->teaching->teaching[] = [9, 10, 4];
    $this->scene->teaching->teaching[] = [9, 11, 4];
    $this->scene->calendar->terms[2]['status'] = 'open';
    $this->scene->calendar->terms[3]['status'] = 'open';
    $this->scene->enrollments->enroll(1, 10, name: 'Pérez, Ana');
    $this->scene->enrollments->enroll(2, 10, from: '2026-10-15', name: 'Álvarez, Luis');
    // Moved from B to A on October 10.
    $this->scene->enrollments->enroll(3, 11, until: '2026-10-10', name: 'Mora, Eva');
    $this->scene->enrollments->enroll(3, 10, from: '2026-10-10', name: 'Mora, Eva');
    sheetPlan(10, 1, [['Prueba', '2026-10-05', '50'], ['Taller', '2026-11-03', '50']]);
    sheetPlan(11, 1, [['Examen', '2026-10-01', '100']]);
});

function sheetPlan(int $section, int $term, array $activities): void
{
    $scene = test()->scene;
    $dates = ['1' => '2026-', '2' => '2027-', '3' => '2027-'];
    (new SavePlanHandler($scene->store, $scene->store, $scene->teaching, $scene->calendar, $scene->permissions, $scene->audit, $scene->transactions))(
        new SavePlanCommand(2, $section, 4, $term, array_map(fn (array $activity) => new ActivityInput(null, $activity[0], $activity[1], $activity[2]), $activities), false),
    );
}

function sheetGrades(int $activity, array $grades): void
{
    $scene = test()->scene;
    $scene->clock->now = new DateTimeImmutable('2026-11-10 10:00');
    (new SaveActivityGradesHandler($scene->store, $scene->store, $scene->teaching, $scene->calendar, $scene->enrollments, $scene->permissions, $scene->clock, $scene->audit, $scene->transactions))($activity, $grades);
}

function sheet(int $section = 10, int $term = 1): GradebookView
{
    $scene = test()->scene;

    return (new GradebookQuery($scene->store, $scene->store, $scene->teaching, $scene->calendar, $scene->enrollments, $scene->permissions, $scene->store))(2, $section, 4, $term);
}

it('shows the activities and the grades of the students of the section, NA included, with the grade of the term', function () {
    sheetGrades(1, [1 => '9,5']);
    sheetGrades(2, [1 => '10', 2 => '20', 3 => 'NP']);
    $sheet = sheet();

    expect(array_map(fn ($activity) => [$activity->name, $activity->weight], $sheet->activities))->toBe([['Prueba', '50'], ['Taller', '50']])
        ->and(array_map(fn ($row) => [$row->fullName, $row->marks, $row->termScore, $row->termState], $sheet->rows))->toBe([
            ['Álvarez, Luis', [1 => 'NA', 2 => '20'], 20, 'scored'],
            // The Examen of her former section counts too, and it has no grade yet.
            ['Mora, Eva', [1 => 'NA', 2 => 'NP'], null, 'incomplete'],
            ['Pérez, Ana', [1 => '9,5', 2 => '10'], 10, 'scored'],
        ])
        ->and($sheet->canGrade)->toBeTrue()
        ->and($sheet->editable)->toBeTrue();
});

it('the grade of the term of a student who changed section counts the activities of both', function () {
    sheetGrades(2, [3 => '18']);
    $this->scene->clock->now = new DateTimeImmutable('2026-10-08 10:00');
    (new SaveActivityGradesHandler($this->scene->store, $this->scene->store, $this->scene->teaching, $this->scene->calendar, $this->scene->enrollments, $this->scene->permissions, $this->scene->clock, $this->scene->audit, $this->scene->transactions))(3, [3 => '11']);
    $eva = array_values(array_filter(sheet()->rows, fn ($row) => $row->fullName === 'Mora, Eva'))[0];

    // Examen (B, 100 %) 11, Taller (A, 50 %) 18; Prueba of A was before she arrived: (11 × 100 + 18 × 50) / 150 = 13,33.
    expect($eva->termScore)->toBe(13)
        ->and($eva->marks)->toBe([1 => 'NA', 2 => '18']);
});

it('is incomplete while an activity lacks its grade, and the final waits for the three terms', function () {
    sheetGrades(1, [1 => '15']);
    $ana = array_values(array_filter(sheet()->rows, fn ($row) => $row->fullName === 'Pérez, Ana'))[0];

    expect($ana->termState)->toBe('incomplete')
        ->and($ana->termScore)->toBeNull()
        ->and($ana->finalState)->toBe('incomplete');

    sheetGrades(2, [1 => '15']);
    sheetPlan(10, 2, [['Prueba', '2027-02-01', '100']]);
    sheetPlan(10, 3, [['Prueba', '2027-05-01', '100']]);
    $this->scene->clock->now = new DateTimeImmutable('2027-06-01 10:00');
    (new SaveActivityGradesHandler($this->scene->store, $this->scene->store, $this->scene->teaching, $this->scene->calendar, $this->scene->enrollments, $this->scene->permissions, $this->scene->clock, $this->scene->audit, $this->scene->transactions))(4, [1 => '9']);
    (new SaveActivityGradesHandler($this->scene->store, $this->scene->store, $this->scene->teaching, $this->scene->calendar, $this->scene->enrollments, $this->scene->permissions, $this->scene->clock, $this->scene->audit, $this->scene->transactions))(5, [1 => '6']);
    $ana = array_values(array_filter(sheet()->rows, fn ($row) => $row->fullName === 'Pérez, Ana'))[0];

    // (15 + 9 + 6) / 3 = 10.
    expect($ana->finalScore)->toBe(10)
        ->and($ana->finalState)->toBe('scored')
        ->and($ana->failed)->toBeFalse();
});

it('a teacher sees only its subjects, a coordinator its grades, a manager everything', function () {
    $this->scene->permissions->accountId = 8;

    expect(fn () => sheet())->toThrow(EvaluationNotFound::class);

    $this->scene->teaching->coordinated[8] = ['media_3'];

    expect(sheet()->canGrade)->toBeFalse();

    $this->scene->teaching->coordinated[8] = [];
    $this->scene->permissions->seesAll = true;

    expect(sheet()->rows)->toHaveCount(3)
        ->and(fn () => sheet(section: 99))->toThrow(EvaluationNotFound::class);
});

it('names its subject, and does not exist for a subject out of the curriculum nor out of Media General', function () {
    $this->scene->permissions->seesAll = true;
    $scene = $this->scene;
    $query = new GradebookQuery($scene->store, $scene->store, $scene->teaching, $scene->calendar, $scene->enrollments, $scene->permissions, $scene->store);

    expect(sheet()->subject)->toBe('Matemática')
        ->and(fn () => $query(2, 10, 9, 1))->toThrow(EvaluationNotFound::class)
        ->and(fn () => $query(2, 20, 4, 1))->toThrow(EvaluationNotFound::class);
});
