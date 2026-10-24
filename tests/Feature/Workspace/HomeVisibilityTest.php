<?php

use Illuminate\Support\Facades\DB;

require_once dirname(__DIR__, 2).'/Fixtures/AcademicCalendar/calendar_helpers.php';
require_once dirname(__DIR__, 2).'/Fixtures/Workspace/home_helpers.php';

/**
 * SPEC-013 NAV-R04 – R10 over HTTP: what each home screen sends to the
 * browser, and what it never sends.
 */
beforeEach(function () {
    $this->institution = tenant(['education_levels' => ['primaria'], 'dea_code' => null]);
    useTenant($this->institution);
    storedSchoolYear(2026, 'active');
});

function homeProps(object $user, string $path): array
{
    $home = null;

    test()->actingAs($user)->get(tenantUrl(test()->institution, $path))
        ->assertOk()
        ->assertInertia(function ($page) use (&$home) {
            $home = $page->toArray()['props']['home'];
        });

    return $home;
}

test('the Administrador sees pending tasks with their links, indicators, the recent log and shortcuts', function () {
    $admin = userWithRole($this->institution, 'Administrador');
    useTenant($this->institution);
    seedInvitation($this->institution->id, 'nuevo@correo.ve', now()->addDays(3));

    $home = homeProps($admin, '/admin');
    $pending = collect($home['pending'])->keyBy('key');

    expect($pending->keys()->all())->toContain('grades_without_sections', 'pending_invitations', 'missing_dea_code', 'missing_director_signature')
        ->and($pending['grades_without_sections']['href'])->toBe(tenantUrl($this->institution, '/estructura'))
        ->and($pending['missing_dea_code']['href'])->toBe(tenantUrl($this->institution, '/plantel'))
        ->and(collect($home['indicators'])->pluck('value', 'key')->only(['school_year', 'open_term', 'sections'])->all())
        ->toBe(['school_year' => '2026-2027', 'open_term' => 1, 'sections' => 0])
        ->and($home['recentActivity'])->toBeArray()
        ->and(collect($home['shortcuts'])->pluck('key')->all())->toContain('structure', 'users', 'audit')
        ->and($home['shortcuts'][0])->toHaveKeys(['label', 'description', 'href']);
});

test('the Director gets no operational blocks, and the ones of its teaching', function () {
    $director = userWithRole($this->institution, 'Director');
    $director->assignRole('Docente');

    $home = homeProps($director, '/director');

    expect(collect($home['pending'])->pluck('key')->all())->not->toContain('pending_invitations', 'missing_dea_code')
        ->and($home['recentActivity'])->toBeNull()
        ->and($home['myClasses'])->toBe(['hasActiveYear' => true, 'teaching' => [], 'guiding' => []]);
});

test('a Teacher gets its classes and none of the institution figures', function () {
    $teacher = userWithRole($this->institution, 'Docente');

    $home = homeProps($teacher, '/teacher');

    expect($home['pending'])->toBeNull()
        ->and($home['indicators'])->toBeNull()
        ->and($home['recentActivity'])->toBeNull()
        ->and($home['myClasses'])->not->toBeNull();
});

test('a Guardian sees its students by name and link, never their health data', function () {
    $parent = userWithRole($this->institution, 'Representante');
    useTenant($this->institution);
    $ana = seedStudent($this->institution->id, 'V11111111111');
    seedStudent($this->institution->id, 'V22222222222', name: 'Otro');
    $guardian = seedGuardian($this->institution->id, $parent->id, 'V15432100');
    seedGuardianship($this->institution->id, $ana, $guardian);

    $home = homeProps($parent, '/guardian');

    expect($home['myStudents']['students'])->toBe([['id' => $ana, 'name' => 'Pérez, Ana', 'href' => tenantUrl($this->institution, "/mis-representados/{$ana}")]])
        ->and(json_encode($home))->not->toContain('Penicilina')->not->toContain('Otro');
});

test('a Student is told its content is to come', function () {
    expect(homeProps(userWithRole($this->institution, 'Estudiante'), '/student')['notices'])->toBe(['student']);
});

test('the Coordinator sees only the grades it coordinates', function () {
    $coordinator = userWithRole($this->institution, 'Coordinador');
    useTenant($this->institution);
    $year = (int) DB::table('school_years')->where('status', 'active')->value('id');
    DB::table('sections')->insert([
        ['school_year_id' => $year, 'grade' => 'primaria_1', 'letter' => 'A', 'capacity' => 30],
        ['school_year_id' => $year, 'grade' => 'primaria_2', 'letter' => 'A', 'capacity' => 25],
    ]);
    DB::table('coordinator_assignments')->insert(['school_year_id' => $year, 'account_id' => $coordinator->id, 'level' => 'primaria', 'grade' => 'primaria_2']);

    $home = homeProps($coordinator, '/coordinator');

    expect(collect($home['indicators'])->pluck('value', 'key')->only(['sections', 'capacity'])->all())->toBe(['sections' => 1, 'capacity' => 25])
        ->and(collect($home['pending'])->pluck('count', 'key')->all())->toBe(['sections_without_guide' => 1]);
});
