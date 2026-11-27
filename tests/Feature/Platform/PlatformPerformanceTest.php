<?php

use AulaX\IdentityAccess\Infrastructure\Persistence\AccountModel as User;
use AulaX\Tenancy\Infrastructure\Persistence\InstitutionModel as Institution;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

require_once dirname(__DIR__, 2).'/Fixtures/Platform/platform_helpers.php';

/**
 * SPEC-014 PLT-NF06: with 500 institutions the list loads within 500 ms,
 * and the usage of a large one within 300 ms (p95), over HTTP with the
 * panel's middleware.
 */
pest()->group('perf');

/**
 * @return float The p95 of 20 warm requests, in milliseconds.
 */
function panelP95(string $url): float
{
    test()->get($url)->assertOk();
    $timings = [];

    foreach (range(1, 20) as $request) {
        $began = hrtime(true);
        test()->get($url)->assertOk();
        $timings[] = (hrtime(true) - $began) / 1e6;
    }
    sort($timings);

    return $timings[(int) floor(count($timings) * 0.95)];
}

test('the list of 500 institutions loads within 500 ms (p95)', function () {
    Institution::factory()->count(500)->create();
    leaveTenant();
    signedInOperator();

    expect(panelP95(adminUrl('/planteles')))->toBeLessThan(500.0)
        ->and(panelP95(adminUrl('/planteles?search=a&status=active')))->toBeLessThan(500.0);
});

test('the usage of a large institution loads within 300 ms (p95)', function () {
    Storage::fake(config('tenancy.files_disk'));
    $institution = tenant();
    userWithRole($institution, 'Administrador');

    foreach (User::factory()->count(99)->for($institution)->create() as $teacher) {
        $teacher->assignRole('Docente');
    }

    foreach (array_chunk(range(1, 2000), 500) as $chunk) {
        DB::table('students')->insert(array_map(fn (int $n) => [
            'institution_id' => $institution->id, 'given_names' => "Estudiante {$n}", 'family_names' => 'Pérez', 'sex' => 'female',
            'birth_date' => '2018-03-04', 'nationality' => 'V', 'identification_type' => 'school_id',
            'identification' => sprintf('V%011d', $n), 'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
        ], $chunk));
    }

    DB::statement("
        insert into audit_entries (institution_id, occurred_at, event_type, subject_type, subject_id, origin, changes)
        select ?, timestamptz '2025-10-11 00:00:00+00' + n * interval '1 minute', 'students.student.registered', 'student', n::text, 'web', '{}'
        from generate_series(1, 20000) as n
    ", [$institution->id]);
    leaveTenant();
    signedInOperator();

    expect(panelP95(adminUrl("/planteles/{$institution->id}")))->toBeLessThan(300.0);
});
