<?php

use AulaX\Students\Application\ListStudents\ListStudentsQuery;
use AulaX\Students\Application\StudentVisibility;
use Illuminate\Support\Facades\DB;

require_once dirname(__DIR__, 2).'/Fixtures/Enrollment/enrollment_helpers.php';

/**
 * SPEC-010 MAT-NF05: «may this person see this student?» answers in under
 * 10 ms (p95), working out who it sees takes under 50 ms (p95) and the list
 * of a teacher with 10 sections loads in under 300 ms (p95).
 */
it('answers who a teacher sees in under 10 ms, and lists its students in under 300 ms', function () {
    $institution = tenant(['education_levels' => ['primaria']]);
    $teacher = userWithRole($institution, 'Docente');
    $this->actingAs($teacher);
    $year = enrollmentYear();
    $rows = [];

    foreach (range(1, 20) as $number) {
        $section = enrollmentSection($year, 'primaria_3', chr(64 + $number), 35);

        if ($number <= 10) {
            DB::table('sections')->where('id', $section)->update(['guide_account_id' => $teacher->id]);
        }

        foreach (range(1, 30) as $seat) {
            $student = DB::table('students')->insertGetId([
                'given_names' => "Estudiante {$number}-{$seat}", 'family_names' => 'Pérez', 'sex' => 'female', 'birth_date' => '2018-03-10',
                'nationality' => 'V', 'identification_type' => 'national_id', 'identification' => 'V'.(30000000 + $number * 100 + $seat), 'status' => 'active',
            ]);
            $rows[] = ['student_id' => $student, 'school_year_id' => $year, 'section_id' => $section, 'grade' => 'primaria_3', 'starts_on' => '2026-09-16', 'registered_at' => '2026-09-16 08:00', 'registered_by' => 1];
        }
    }

    DB::table('enrollments')->insert($rows);
    // Warm up: the first call of the test process loads classes and services.
    app(StudentVisibility::class)->visibleStudents();
    $workedOut = [];
    $sees = [];
    $lists = [];

    foreach (range(1, 40) as $run) {
        $visibility = app(StudentVisibility::class);
        $started = hrtime(true);
        $visibility->visibleStudents();
        $workedOut[] = (hrtime(true) - $started) / 1e6;

        $started = hrtime(true);
        $visibility->sees($rows[$run * 7]['student_id']);
        $sees[] = (hrtime(true) - $started) / 1e6;

        $started = hrtime(true);
        $page = app(ListStudentsQuery::class)('', false, 1);
        $lists[] = (hrtime(true) - $started) / 1e6;
    }

    sort($workedOut);
    sort($sees);
    sort($lists);
    $p95 = fn (array $timings) => $timings[(int) floor(count($timings) * 0.95)];

    expect($page->total)->toBe(300)
        ->and($p95($workedOut))->toBeLessThan(50.0)
        ->and($p95($sees))->toBeLessThan(10.0)
        ->and($p95($lists))->toBeLessThan(300.0);
})->group('perf');
