<?php

use AulaX\Tenancy\Infrastructure\Persistence\InstitutionModel as Institution;
use Illuminate\Support\Facades\DB;

/**
 * SPEC-009 EST-R08.2, R08.3: a guardian with an account sees the basic
 * record of its represented students, and nothing of any other.
 */
beforeEach(function () {
    $this->institution = tenant();
    $this->admin = userWithRole($this->institution, 'Administrador');
    $this->own = registeredWithGuardian($this->institution, 'Ana', 'V11915432100', 'V15432100', 'Penicilina');
    $this->other = registeredWithGuardian($this->institution, 'Luis', 'V11920111222', 'V20111222');
    $this->guardianUser = userWithRole($this->institution, 'Representante', ['email' => 'maria@correo.ve']);
    DB::table('guardians')->where('document', 'V15432100')->update(['account_id' => $this->guardianUser->id]);
});

function registeredWithGuardian(Institution $institution, string $name, string $identification, string $document, ?string $allergies = null): int
{
    test()->actingAs(test()->admin)->post(tenantUrl($institution, '/estudiantes'), [
        'given_names' => $name, 'family_names' => 'Pérez', 'sex' => 'female', 'birth_date' => '2019-03-04', 'nationality' => 'V',
        'identification_type' => 'school_id', 'identification' => $identification, 'allergies' => $allergies,
        'guardians' => [[
            'given_names' => 'Madre', 'family_names' => $name, 'document_type' => 'national_id', 'document' => $document,
            'phone' => '0414', 'relationship' => 'mother', 'is_legal' => true, 'is_primary' => true,
        ]],
    ])->assertSessionHasNoErrors();

    return (int) DB::table('students')->max('id');
}

/**
 * Whether the menu offers «Mis representados» (EST-R08.2, now an entry of
 * SPEC-013 NAV-R03).
 */
function offersMyStudents(iterable $groups): bool
{
    return collect($groups)->flatMap(fn ($group) => $group['entries'])->contains('key', 'my_students');
}

function asGuardian(string $path)
{
    return test()->actingAs(test()->guardianUser)->get(tenantUrl(test()->institution, $path));
}

test('a guardian sees its represented students only', function () {
    asGuardian('/mis-representados')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('MyStudents/Index')
            ->has('students', 1)
            ->where('students.0.id', $this->own)
            ->where('layout.navigation', fn ($groups) => offersMyStudents($groups)));
});

test('a guardian sees the basic record of its student, without health data', function () {
    asGuardian("/mis-representados/{$this->own}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('MyStudents/Show')
            ->where('student.givenNames', 'Ana')
            ->where('student.identification', 'V11915432100')
            ->missing('student.health')
            ->missing('student.hasHealthInfo')
            ->where('student.guardians.0.relationship', 'mother')
            ->missing('student.guardians.0.document'));
});

test('a student it does not represent does not exist for it', function () {
    asGuardian("/mis-representados/{$this->other}")->assertNotFound();
    asGuardian('/mis-representados/999999')->assertNotFound();
});

test('an account that represents no student here has nothing to see', function () {
    $teacher = userWithRole($this->institution, 'Docente');

    $this->actingAs($teacher)->get(tenantUrl($this->institution, '/mis-representados'))->assertForbidden();
    $this->actingAs($teacher)->get(tenantUrl($this->institution, '/estudiantes'))->assertInertia(fn ($page) => $page->where('layout.navigation', fn ($groups) => ! offersMyStudents($groups)));
});

test('the guardian of one institution sees nothing in another', function () {
    $elsewhere = tenant();
    joinInstitution($this->guardianUser, $elsewhere, 'Representante');

    $this->actingAs($this->guardianUser)->get(tenantUrl($elsewhere, '/mis-representados'))->assertForbidden();
    $this->actingAs($this->guardianUser)->get(tenantUrl($elsewhere, "/mis-representados/{$this->own}"))->assertForbidden();
});
