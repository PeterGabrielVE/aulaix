<?php

use Illuminate\Support\Facades\DB;

/**
 * SPEC-008 over HTTP: assigning teachers, guides and coordinators, the
 * warning for people no longer active, and each teacher's own classes.
 */
beforeEach(function () {
    $this->institution = tenant(['education_levels' => ['primaria']]);
    $this->admin = userWithRole($this->institution, 'Administrador');
    $this->teacher = userWithRole($this->institution, 'Docente', ['name' => 'Ana Pérez']);
    $this->coordinator = userWithRole($this->institution, 'Coordinador', ['name' => 'Carla Ruiz']);
    $this->year = DB::table('school_years')->insertGetId(['starts_on' => '2026-09-16', 'ends_on' => '2027-07-15', 'status' => 'active']);
    $this->math = DB::table('subjects')->insertGetId(['code' => 'T-'.uniqid(), 'name' => 'Matemática', 'education_level' => 'primaria']);
    $this->section = DB::table('sections')->insertGetId(['school_year_id' => $this->year, 'grade' => 'primaria_1', 'letter' => 'A', 'capacity' => 30]);
    DB::table('curriculum_subjects')->insert(['school_year_id' => $this->year, 'grade' => 'primaria_1', 'subject_id' => $this->math]);
});

function asStructureAdmin(string $method, string $path, array $data = [])
{
    return test()->actingAs(test()->admin)->{$method}(tenantUrl(test()->institution, $path), $data);
}

test('a manager assigns the teacher of a subject, the guide and a coordinator', function () {
    asStructureAdmin('put', "/estructura/secciones/{$this->section}/materias/{$this->math}/docente", ['account_id' => $this->teacher->id])->assertSessionHasNoErrors();
    asStructureAdmin('put', "/estructura/secciones/{$this->section}/guia", ['account_id' => $this->teacher->id])->assertSessionHasNoErrors();
    asStructureAdmin('post', "/estructura/{$this->year}/coordinadores", ['account_id' => $this->coordinator->id, 'level' => 'primaria', 'grades' => ['primaria_1']])->assertSessionHasNoErrors();

    expect(DB::table('teaching_assignments')->value('account_id'))->toBe($this->teacher->id)
        ->and(DB::table('sections')->value('guide_account_id'))->toBe($this->teacher->id)
        ->and(DB::table('coordinator_assignments')->first())->account_id->toBe($this->coordinator->id)->grade->toBe('primaria_1');

    asStructureAdmin('delete', "/estructura/{$this->year}/coordinadores/{$this->coordinator->id}/primaria")->assertSessionHasNoErrors();

    expect(DB::table('coordinator_assignments')->count())->toBe(0);
});

test('only a teacher of the institution can teach or guide', function () {
    asStructureAdmin('put', "/estructura/secciones/{$this->section}/materias/{$this->math}/docente", ['account_id' => $this->coordinator->id])
        ->assertSessionHasErrors(['account_id' => 'Esa persona no es docente activo del plantel.']);
});

test('the screen names the people assigned and warns of those no longer active', function () {
    asStructureAdmin('put', "/estructura/secciones/{$this->section}/materias/{$this->math}/docente", ['account_id' => $this->teacher->id]);
    DB::table('institution_memberships')->where('user_id', $this->teacher->id)->update(['status' => 'ended']);

    asStructureAdmin('get', '/estructura')
        ->assertInertia(fn ($page) => $page
            ->where('structure.sections.0.teachers.'.$this->math, $this->teacher->id)
            ->where('structure.people.'.$this->teacher->id.'.name', 'Ana Pérez')
            ->where('structure.people.'.$this->teacher->id.'.active', false)
            ->where('structure.orphans.0.accountId', $this->teacher->id)
            ->where('structure.coordinatorCandidates.0.name', 'Carla Ruiz'));
});

test('a teacher sees its own classes', function () {
    asStructureAdmin('put', "/estructura/secciones/{$this->section}/materias/{$this->math}/docente", ['account_id' => $this->teacher->id]);

    $this->actingAs($this->teacher)->get(tenantUrl($this->institution, '/mis-asignaciones'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('MyAssignments/Index')
            ->where('assignments.teaching.0.subject', 'Matemática')
            ->where('assignments.teaching.0.section', '1.er grado A'));
});

test('who may not manage the structure gets 403 assigning people', function (string $method, string $path) {
    $path = str_replace(['{year}', '{section}', '{subject}', '{account}'], [$this->year, $this->section, $this->math, $this->coordinator->id], $path);

    $this->actingAs($this->teacher)->{$method}(tenantUrl($this->institution, $path), ['account_id' => $this->teacher->id, 'level' => 'primaria'])->assertForbidden();
})->with([
    ['put', '/estructura/secciones/{section}/materias/{subject}/docente'],
    ['put', '/estructura/secciones/{section}/guia'],
    ['post', '/estructura/{year}/coordinadores'],
    ['delete', '/estructura/{year}/coordinadores/{account}/primaria'],
]);
