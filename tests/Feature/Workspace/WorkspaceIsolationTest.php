<?php

use Illuminate\Support\Facades\DB;

require_once dirname(__DIR__, 2).'/Fixtures/AcademicCalendar/calendar_helpers.php';
require_once dirname(__DIR__, 2).'/Fixtures/Workspace/home_helpers.php';

/**
 * SPEC-013 NAV-R10.1 (S-01, S-02): the home screen counts only the open
 * institution, whatever another one holds.
 */
test('the home screen counts only the open institution', function () {
    $sur = tenant(['education_levels' => ['primaria']]);
    useTenant($sur);
    $surYear = storedSchoolYear(2026, 'active');
    DB::table('sections')->insert(['school_year_id' => $surYear, 'grade' => 'primaria_1', 'letter' => 'A', 'capacity' => 30]);
    seedStudent($sur->id, 'V11111111111');
    seedStudent($sur->id, 'V22222222222');
    $surGuardian = seedGuardian($sur->id, null, 'V15432100');
    seedCorrection($sur->id, $surGuardian, null);
    seedInvitation($sur->id, 'sur@correo.ve', now()->addDay());
    userWithRole($sur, 'Docente');
    DB::table('audit_entries')->insert(['institution_id' => $sur->id, 'occurred_at' => now(), 'event_type' => 'students.student.registered', 'subject_type' => 'student', 'subject_id' => '1', 'origin' => 'web']);

    $norte = tenant(['education_levels' => ['primaria']]);
    $admin = userWithRole($norte, 'Administrador');
    useTenant($norte);
    storedSchoolYear(2026, 'active');

    $home = null;
    $this->actingAs($admin)->get(tenantUrl($norte, '/admin'))
        ->assertOk()
        ->assertInertia(function ($page) use (&$home) {
            $home = $page->toArray()['props']['home'];
        });

    $indicators = collect($home['indicators'])->pluck('value', 'key');
    $pending = collect($home['pending'])->pluck('count', 'key');

    expect($indicators['sections'])->toBe(0)
        ->and($indicators['active_students'])->toBe(0)
        ->and($indicators['staff']['Docente'])->toBe(0)
        ->and($pending->has('pending_invitations'))->toBeFalse()
        ->and($pending->has('pending_corrections'))->toBeFalse()
        ->and(collect($home['recentActivity'])->pluck('event')->all())->not->toContain('students.student.registered');
})->group('rls');
