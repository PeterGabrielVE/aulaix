<?php

use AulaX\Tenancy\Infrastructure\Persistence\InstitutionModel as Institution;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;

/**
 * SPEC-009 over HTTP: the students screens, who may see and change them, and
 * the health data only for managers.
 */
function studentPayload(array $data = []): array
{
    return $data + [
        'given_names' => 'Ana Sofía',
        'family_names' => 'Pérez Mora',
        'sex' => 'female',
        'birth_date' => '2019-03-04',
        'nationality' => 'V',
        'identification_type' => 'school_id',
        'identification' => 'V11915432100',
        'birth_country' => 'VE',
        'birth_state_id' => null,
        'parish_id' => null,
        'address' => 'Calle 5',
        'emergency_phone' => null,
        'allergies' => 'Penicilina',
        'medical_conditions' => null,
        'disability' => null,
        'guardians' => [[
            'guardian_id' => null, 'given_names' => 'María', 'family_names' => 'Mora', 'document_type' => 'national_id',
            'document' => 'V-15.432.100', 'phone' => '0414-5550000', 'email' => null, 'address' => null,
            'relationship' => 'mother', 'is_legal' => true, 'is_primary' => true,
        ]],
    ];
}

function asStudentsMember(Institution $institution, string $role, string $method, string $path, array $data = []): TestResponse
{
    return test()->actingAs(userWithRole($institution, $role))->{$method}(tenantUrl($institution, $path), $data);
}

function registeredStudentId(): int
{
    return (int) DB::table('students')->max('id');
}

test('a manager registers a student with its guardian and lands on its record', function (string $role) {
    $institution = tenant();

    asStudentsMember($institution, $role, 'post', '/estudiantes', studentPayload())
        ->assertSessionHasNoErrors()
        ->assertRedirect(tenantUrl($institution, '/estudiantes/'.registeredStudentId()));

    expect(DB::table('students')->value('identification'))->toBe('V11915432100')
        ->and(DB::table('guardians')->value('document'))->toBe('V15432100');
})->with(['Administrador', 'Director']);

test('the form errors come back by field, guardians included', function () {
    $institution = tenant();
    $payload = studentPayload(['given_names' => '', 'identification' => 'V12015432100']);
    $payload['guardians'][0]['phone'] = '';

    asStudentsMember($institution, 'Administrador', 'post', '/estudiantes', $payload)
        ->assertSessionHasErrors(['given_names', 'guardians.0.phone']);

    expect(DB::table('students')->count())->toBe(0);
});

test('teachers and coordinators see the students, without their health data', function (string $role) {
    $institution = tenant(['education_levels' => ['primaria']]);
    asStudentsMember($institution, 'Administrador', 'post', '/estudiantes', studentPayload());
    $id = registeredStudentId();
    // SPEC-010 MAT-R08: they see the students of their sections or grades, so this one is in theirs.
    $member = userWithRole($institution, $role);
    useTenant($institution);
    $year = DB::table('school_years')->insertGetId(['starts_on' => '2026-09-16', 'ends_on' => '2027-07-15', 'status' => 'active']);
    $section = DB::table('sections')->insertGetId(['school_year_id' => $year, 'grade' => 'primaria_1', 'letter' => 'A', 'capacity' => 30, 'guide_account_id' => $role === 'Docente' ? $member->id : null]);
    DB::table('coordinator_assignments')->insert(['school_year_id' => $year, 'account_id' => $member->id, 'level' => 'primaria', 'grade' => 'primaria_1']);
    DB::table('enrollments')->insert(['student_id' => $id, 'school_year_id' => $year, 'section_id' => $section, 'grade' => 'primaria_1', 'starts_on' => '2026-09-16', 'registered_at' => now(), 'registered_by' => $member->id]);

    test()->actingAs($member)->get(tenantUrl($institution, '/estudiantes'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Students/Index')->where('students.0.fullName', 'Pérez Mora, Ana Sofía')->where('canManage', false));

    test()->actingAs($member)->get(tenantUrl($institution, "/estudiantes/{$id}"))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Students/Show')
            ->where('student.health', null)
            ->where('student.hasHealthInfo', true)
            ->where('student.guardians.0.document', 'V15432100'));
})->with(['Coordinador', 'Docente']);

test('managers see the health data', function () {
    $institution = tenant();
    asStudentsMember($institution, 'Administrador', 'post', '/estudiantes', studentPayload());

    asStudentsMember($institution, 'Director', 'get', '/estudiantes/'.registeredStudentId())
        ->assertInertia(fn ($page) => $page->where('student.health.allergies', 'Penicilina')->where('canManage', true));
});

test('who may not manage students gets 403 on every change', function (string $method, string $path) {
    $institution = tenant();
    asStudentsMember($institution, 'Administrador', 'post', '/estudiantes', studentPayload());
    $path = str_replace('{id}', (string) registeredStudentId(), $path);

    asStudentsMember($institution, 'Docente', $method, $path, studentPayload())->assertForbidden();
})->with([
    ['get', '/estudiantes/nuevo'],
    ['post', '/estudiantes'],
    ['get', '/estudiantes/{id}/editar'],
    ['put', '/estudiantes/{id}'],
    ['put', '/estudiantes/{id}/salud'],
    ['post', '/estudiantes/{id}/retiro'],
    ['post', '/estudiantes/{id}/reincorporacion'],
    ['get', '/representantes/buscar?document_type=national_id&document=V15432100'],
]);

test('a guardian or a student without ver-estudiantes does not see the list', function (string $role) {
    $institution = tenant();

    asStudentsMember($institution, $role, 'get', '/estudiantes')->assertForbidden();
})->with(['Representante', 'Estudiante']);

test('a manager edits, changes the health data, withdraws and reinstates a student', function () {
    $institution = tenant();
    $admin = userWithRole($institution, 'Administrador');
    $this->actingAs($admin)->post(tenantUrl($institution, '/estudiantes'), studentPayload());
    $id = registeredStudentId();
    $guardian = (int) DB::table('guardians')->value('id');

    $edited = studentPayload(['given_names' => 'Ana', 'identification_type' => 'national_id', 'identification' => 'V30123456']);
    $edited['guardians'][0]['guardian_id'] = $guardian;
    $this->actingAs($admin)->put(tenantUrl($institution, "/estudiantes/{$id}"), $edited)->assertSessionHasNoErrors();
    $this->actingAs($admin)->put(tenantUrl($institution, "/estudiantes/{$id}/salud"), ['allergies' => null, 'medical_conditions' => 'Asma', 'disability' => null])->assertSessionHasNoErrors();
    $this->actingAs($admin)->post(tenantUrl($institution, "/estudiantes/{$id}/retiro"), ['withdrawn_on' => '2026-10-01', 'reason' => 'moved', 'note' => null])->assertSessionHasNoErrors();

    expect(DB::table('students')->where('id', $id)->first())
        ->given_names->toBe('Ana')->identification->toBe('V30123456')->medical_conditions->toBe('Asma')->status->toBe('withdrawn')
        ->and(DB::table('student_previous_identifications')->value('identification'))->toBe('V11915432100');

    $this->actingAs($admin)->post(tenantUrl($institution, "/estudiantes/{$id}/reincorporacion"))->assertSessionHasNoErrors();

    expect(DB::table('students')->where('id', $id)->value('status'))->toBe('active');
});

test('the form finds a guardian the institution already has', function () {
    $institution = tenant();
    asStudentsMember($institution, 'Administrador', 'post', '/estudiantes', studentPayload());

    asStudentsMember($institution, 'Director', 'getJson', '/representantes/buscar?document_type=national_id&document=V15432100')
        ->assertOk()
        ->assertJsonPath('data.given_names', 'María');
});

test('a manager corrects a guardian', function () {
    $institution = tenant();
    asStudentsMember($institution, 'Administrador', 'post', '/estudiantes', studentPayload());
    $guardian = (int) DB::table('guardians')->value('id');

    asStudentsMember($institution, 'Director', 'put', "/representantes/{$guardian}", [
        'given_names' => 'María José', 'family_names' => 'Mora', 'document_type' => 'national_id', 'document' => 'V15432100',
        'phone' => '0424-1112233', 'email' => null, 'address' => null,
    ])->assertSessionHasNoErrors();

    expect(DB::table('guardians')->value('phone'))->toBe('0424-1112233');
});

test('a student of another institution does not exist here, whatever the id', function () {
    $other = tenant();
    asStudentsMember($other, 'Administrador', 'post', '/estudiantes', studentPayload());
    $id = registeredStudentId();
    $institution = tenant();

    asStudentsMember($institution, 'Administrador', 'get', "/estudiantes/{$id}")->assertNotFound();
});

test('the permissions are shared with the pages', function () {
    $institution = tenant();

    asStudentsMember($institution, 'Director', 'get', '/estudiantes')
        ->assertInertia(fn ($page) => $page->where('auth.can.viewStudents', true)->where('auth.can.manageStudents', true));
});
