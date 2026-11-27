<?php

use AulaX\Enrollment\Application\Roster\EnrollmentPeriodsQuery;
use AulaX\Enrollment\Application\Roster\PeriodView;

require_once dirname(__DIR__, 3).'/Fixtures/Enrollment/enrollment_helpers.php';

/**
 * SPEC-011 (design): when each student was in each section of a year, for
 * the activities that apply to it; annulled enrollments never were.
 */
it('gives the periods of the students of a year, oldest first, leaving out annulled ones', function () {
    tenant();
    $year = enrollmentYear();
    $sectionA = enrollmentSection($year, letter: 'A');
    $sectionB = enrollmentSection($year, letter: 'B');
    $ana = enrollmentStudent('Pérez', 'Ana');
    $luis = enrollmentStudent('Álvarez', 'Luis');
    enrollmentRow($ana, $year, $sectionA, ['ends_on' => '2026-10-15', 'end_reason' => 'section_change']);
    enrollmentRow($ana, $year, $sectionB, ['starts_on' => '2026-10-15']);
    enrollmentRow($luis, $year, $sectionA, ['ends_on' => '2026-09-16', 'end_reason' => 'annulled', 'annulment_reason' => 'duplicate', 'annulled_by' => 1, 'annulled_at' => '2026-09-16 09:00']);

    $periods = app(EnrollmentPeriodsQuery::class)($year, [$ana, $luis]);

    expect($periods)->toEqual([
        $ana => [
            new PeriodView($sectionA, 'primaria_3', '2026-09-16', '2026-10-15'),
            new PeriodView($sectionB, 'primaria_3', '2026-10-15', null),
        ],
    ]);
});
