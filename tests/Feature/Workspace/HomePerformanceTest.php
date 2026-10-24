<?php

use AulaX\IdentityAccess\Infrastructure\Persistence\AccountModel as User;
use AulaX\Workspace\Application\ShowHome\ShowHomeQuery;
use AulaX\Workspace\Domain\HomeKind;
use Illuminate\Support\Facades\DB;

require_once dirname(__DIR__, 2).'/Fixtures/AcademicCalendar/calendar_helpers.php';

/**
 * SPEC-013 NAV-NF02: the Director's home screen of an institution with 2000
 * students, 60 sections and 100 members loads within 300 ms (p95).
 */
test('the Director home of a large institution loads within 300 ms (p95)', function () {
    $institution = tenant(['education_levels' => ['primaria']]);
    $director = userWithRole($institution, 'Director');
    useTenant($institution);
    $year = storedSchoolYear(2026, 'active');

    foreach (User::factory()->count(99)->for($institution)->create() as $teacher) {
        $teacher->assignRole('Docente');
    }

    $sections = [];
    foreach (['primaria_1', 'primaria_2', 'primaria_3', 'primaria_4', 'primaria_5', 'primaria_6'] as $grade) {
        foreach (range('A', 'J') as $letter) {
            $sections[] = ['institution_id' => $institution->id, 'school_year_id' => $year, 'grade' => $grade, 'letter' => $letter, 'capacity' => 30];
        }
    }
    DB::table('sections')->insert($sections);

    foreach (array_chunk(range(1, 2000), 500) as $chunk) {
        DB::table('students')->insert(array_map(fn (int $n) => [
            'institution_id' => $institution->id, 'given_names' => "Estudiante {$n}", 'family_names' => 'Pérez', 'sex' => 'female',
            'birth_date' => '2018-03-04', 'nationality' => 'V', 'identification_type' => 'school_id',
            'identification' => sprintf('V%011d', $n), 'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
        ], $chunk));
    }

    $this->actingAs($director);
    $query = app(ShowHomeQuery::class);
    // A warm request: the permission cache and the prepared queries persist between requests.
    $query($director->id, HomeKind::Director);
    $timings = [];

    foreach (range(1, 20) as $call) {
        app()->forgetScopedInstances();
        $began = hrtime(true);
        $query($director->id, HomeKind::Director);
        $timings[] = (hrtime(true) - $began) / 1e6;
    }
    sort($timings);

    expect($timings[(int) floor(count($timings) * 0.95)])->toBeLessThan(300.0);
})->group('perf');
