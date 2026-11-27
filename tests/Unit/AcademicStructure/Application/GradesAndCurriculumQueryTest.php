<?php

use AulaX\AcademicCalendar\Application\SchoolYearNotFound;
use AulaX\AcademicCalendar\Domain\SchoolYearStatus;
use AulaX\AcademicStructure\Application\Grades\CurriculumQuery;
use AulaX\AcademicStructure\Application\Grades\GradesQuery;
use AulaX\AcademicStructure\Application\Grades\GradeView;
use AulaX\AcademicStructure\Domain\Grade;
use AulaX\Shared\Domain\BusinessRuleViolation;
use Tests\Fixtures\AcademicStructure\StructureScene;

/**
 * SPEC-010 (design): the grades in their official order with their level,
 * and the curriculum of a grade in a year, for the enrollments.
 */
it('lists the 17 grades in order, with their level and name', function () {
    $grades = (new GradesQuery)();

    expect($grades)->toHaveCount(17)
        ->and($grades[0])->toEqual(new GradeView('maternal_1', 'inicial', 'Maternal I'))
        ->and($grades[6])->toEqual(new GradeView('primaria_1', 'primaria', '1.er grado'))
        ->and($grades[16])->toEqual(new GradeView('media_5', 'media_general', '5.º año'));
});

it('gives the subjects of the curriculum of a grade in a year', function () {
    $scene = new StructureScene;
    $scene->year(2, 2026, SchoolYearStatus::Active);
    $structure = $scene->loader()->forYear(2);
    $structure->setCurriculum(Grade::Media1, [4, 5], [4 => 'media_general', 5 => 'media_general'], []);
    $scene->structures->save($structure);

    $query = new CurriculumQuery($scene->loader());

    expect($query(2, 'media_1'))->toBe([4, 5])
        ->and($query(2, 'media_2'))->toBe([])
        ->and(fn () => $query(9, 'media_1'))->toThrow(SchoolYearNotFound::class)
        ->and(fn () => $query(2, 'quinto'))->toThrow(BusinessRuleViolation::class);
});
