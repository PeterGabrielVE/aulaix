<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;

/**
 * SPEC-008 over HTTP: the structure screen, who may see and change it.
 */
beforeEach(function () {
    $this->institution = tenant(['education_levels' => ['primaria']]);
    $this->planned = DB::table('school_years')->insertGetId(['starts_on' => '2026-09-16', 'ends_on' => '2027-07-15', 'status' => 'planned']);
    $this->math = DB::table('subjects')->insertGetId(['code' => 'T-'.uniqid(), 'name' => 'Matemática', 'education_level' => 'primaria']);
});

function asStructureMember(string $role, string $method, string $path, array $data = []): TestResponse
{
    $institution = test()->institution;

    return test()->actingAs(userWithRole($institution, $role))->{$method}(tenantUrl($institution, $path), $data);
}

test('a manager opens sections and sets the curriculum of the planned year', function (string $role) {
    asStructureMember($role, 'post', "/estructura/{$this->planned}/secciones", ['grade' => 'primaria_1', 'letter' => 'a', 'capacity' => 30])
        ->assertSessionHasNoErrors();
    asStructureMember($role, 'put', "/estructura/{$this->planned}/pensum/primaria_1", ['subjects' => [$this->math]])
        ->assertSessionHasNoErrors();

    expect(DB::table('sections')->value('letter'))->toBe('A')
        ->and(DB::table('curriculum_subjects')->value('subject_id'))->toBe($this->math);
})->with(['Administrador', 'Director']);

test('the screen shows the structure to teachers and coordinators, read only', function (string $role) {
    asStructureMember('Administrador', 'post', "/estructura/{$this->planned}/secciones", ['grade' => 'primaria_1', 'letter' => 'A', 'capacity' => 30]);

    asStructureMember($role, 'get', '/estructura')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Structure/Index')
            ->where('structure.schoolYearId', $this->planned)
            ->where('structure.sections.0.letter', 'A')
            ->where('canManage', false)
            ->where('auth.can.viewStructure', true));
})->with(['Coordinador', 'Docente']);

test('the capacity goes over the cap only with a reason', function () {
    asStructureMember('Administrador', 'post', "/estructura/{$this->planned}/secciones", ['grade' => 'primaria_1', 'letter' => 'A', 'capacity' => 36])
        ->assertSessionHasErrors('capacity_reason');
    asStructureMember('Administrador', 'post', "/estructura/{$this->planned}/secciones", ['grade' => 'primaria_1', 'letter' => 'A', 'capacity' => 36, 'capacity_reason' => 'Alta demanda'])
        ->assertSessionHasNoErrors();
    $section = (int) DB::table('sections')->value('id');

    asStructureMember('Director', 'put', "/estructura/secciones/{$section}/cupo", ['capacity' => 30])->assertSessionHasNoErrors();

    expect(DB::table('sections')->where('id', $section)->first())->capacity->toBe(30)->capacity_reason->toBeNull();
});

test('a manager removes a section and copies the structure of the year before', function () {
    DB::table('school_years')->where('id', $this->planned)->update(['status' => 'closed', 'starts_on' => '2025-09-16', 'ends_on' => '2026-07-15']);
    $next = DB::table('school_years')->insertGetId(['starts_on' => '2026-09-16', 'ends_on' => '2027-07-15', 'status' => 'planned']);
    DB::table('sections')->insert([
        ['school_year_id' => $this->planned, 'grade' => 'primaria_1', 'letter' => 'A', 'capacity' => 30],
        ['school_year_id' => $this->planned, 'grade' => 'primaria_1', 'letter' => 'B', 'capacity' => 30],
    ]);

    asStructureMember('Administrador', 'post', "/estructura/{$next}/copiar")->assertSessionHasNoErrors();
    $copied = (int) DB::table('sections')->where('school_year_id', $next)->where('letter', 'B')->value('id');
    asStructureMember('Administrador', 'delete', "/estructura/secciones/{$copied}")->assertSessionHasNoErrors();

    expect(DB::table('sections')->where('school_year_id', $next)->pluck('letter')->all())->toBe(['A']);
});

test('who may not manage the structure gets 403 on every change', function (string $method, string $path) {
    $section = DB::table('sections')->insertGetId(['school_year_id' => $this->planned, 'grade' => 'primaria_1', 'letter' => 'A', 'capacity' => 30]);
    $path = str_replace(['{year}', '{section}'], [$this->planned, $section], $path);

    asStructureMember('Docente', $method, $path, ['grade' => 'primaria_1', 'letter' => 'B', 'capacity' => 30, 'subjects' => []])->assertForbidden();
})->with([
    ['post', '/estructura/{year}/secciones'],
    ['put', '/estructura/secciones/{section}/cupo'],
    ['delete', '/estructura/secciones/{section}'],
    ['put', '/estructura/{year}/pensum/primaria_1'],
    ['post', '/estructura/{year}/copiar'],
]);

test('a member without ver-estructura does not see the screen', function () {
    asStructureMember('Representante', 'get', '/estructura')->assertForbidden();
});

test('the structure of another institution does not exist here', function () {
    $section = DB::table('sections')->insertGetId(['school_year_id' => $this->planned, 'grade' => 'primaria_1', 'letter' => 'A', 'capacity' => 30]);
    $other = tenant(['education_levels' => ['primaria']]);

    $this->actingAs(userWithRole($other, 'Administrador'))->delete(tenantUrl($other, "/estructura/secciones/{$section}"))->assertNotFound();
    $this->actingAs(userWithRole($other, 'Administrador'))->post(tenantUrl($other, "/estructura/{$this->planned}/secciones"), ['grade' => 'primaria_1', 'letter' => 'A', 'capacity' => 30])->assertNotFound();
});
