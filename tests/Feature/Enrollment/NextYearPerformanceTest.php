<?php

use AulaX\Enrollment\Application\NextYear\EnrollNextYearCommand;
use AulaX\Enrollment\Application\NextYear\EnrollNextYearHandler;
use Illuminate\Support\Facades\DB;

require_once dirname(__DIR__, 2).'/Fixtures/Enrollment/enrollment_helpers.php';

/**
 * SPEC-010 MAT-NF04: enrolling 600 students in the next year takes under
 * 10 seconds.
 */
it('enrolls 600 students in the next year in under 10 seconds', function () {
    $institution = tenant();
    $this->actingAs(userWithRole($institution, 'Administrador'));
    $year = enrollmentYear(2026);
    $next = enrollmentYear(2027, 'planned');
    $from = [];
    $to = [];

    foreach (range(1, 20) as $number) {
        $from[] = enrollmentSection($year, 'primaria_3', chr(64 + $number), 35);
        $to[] = enrollmentSection($next, 'primaria_4', chr(64 + $number), 35);
    }

    $placements = [];
    $rows = [];

    foreach (range(1, 600) as $number) {
        $student = DB::table('students')->insertGetId([
            'given_names' => "Estudiante {$number}", 'family_names' => 'Pérez', 'sex' => 'female', 'birth_date' => '2018-03-10',
            'nationality' => 'V', 'identification_type' => 'national_id', 'identification' => 'V'.(30000000 + $number), 'status' => 'active',
        ]);
        $rows[] = [
            'student_id' => $student, 'school_year_id' => $year, 'section_id' => $from[$number % 20], 'grade' => 'primaria_3',
            'starts_on' => '2026-09-16', 'registered_at' => '2026-09-16 08:00', 'registered_by' => 1,
            'outcome' => 'promoted', 'outcome_recorded_at' => '2027-07-10 08:00', 'outcome_recorded_by' => 1,
        ];
        $placements[$student] = $to[$number % 20];
    }

    DB::table('enrollments')->insert($rows);

    $started = hrtime(true);
    $count = app(EnrollNextYearHandler::class)(new EnrollNextYearCommand($year, $placements, [], 1));
    $seconds = (hrtime(true) - $started) / 1e9;

    expect($count)->toBe(600)
        ->and($seconds)->toBeLessThan(10.0);
})->group('perf');
