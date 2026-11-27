<?php

use Illuminate\Support\Facades\DB;

require_once dirname(__DIR__, 2).'/Fixtures/Evaluation/evaluation_helpers.php';

/**
 * SPEC-011 EVA-R07, NF01: only whoever teaches a subject grades it; a
 * coordinator and the managers see it; anyone else, not even that it
 * exists. Every check in the use case, whatever the route lets through.
 */
beforeEach(function () {
    foreach (evaluationSetting() as $name => $value) {
        $this->{$name} = $value;
    }

    $this->activity = evaluationActivityRow(evaluationPlanRow($this->year, $this->section, $this->math));
});

function permissionSheetUrl(?int $subject = null): string
{
    $test = test();

    return tenantUrl($test->institution, "/notas/{$test->year}/secciones/{$test->section}/materias/".($subject ?? $test->math).'/lapsos/1');
}

it('another teacher does not see the sheet, and can not grade nor plan it', function () {
    $other = userWithRole($this->institution, 'Docente');
    DB::table('teaching_assignments')->insert(['section_id' => $this->section, 'subject_id' => $this->physics, 'account_id' => $other->id]);

    $this->actingAs($other)->get(permissionSheetUrl())->assertNotFound();
    $this->actingAs($other)->put(tenantUrl($this->institution, "/notas/actividades/{$this->activity}"), ['grades' => [$this->ana => '20']])->assertForbidden();
    $this->actingAs($other)->put(permissionSheetUrl().'/plan', ['activities' => [['name' => 'X', 'date' => '2026-10-05', 'weight' => '100']]])->assertForbidden();

    $this->actingAs($other)->get(tenantUrl($this->institution, '/notas'))->assertInertia(fn ($page) => $page
        ->where('list.sheets.0.subject', 'Física')
        ->has('list.sheets', 1));

    expect(DB::table('activity_grades')->count())->toBe(0);
});

it('a coordinator of the grade sees the sheet but does not grade it', function () {
    $coordinator = userWithRole($this->institution, 'Coordinador');
    DB::table('coordinator_assignments')->insert(['school_year_id' => $this->year, 'account_id' => $coordinator->id, 'level' => 'media_general', 'grade' => 'media_3']);

    $this->actingAs($coordinator)->get(permissionSheetUrl())->assertOk()->assertInertia(fn ($page) => $page->where('sheet.canGrade', false));
    $this->actingAs($coordinator)->put(tenantUrl($this->institution, "/notas/actividades/{$this->activity}"), ['grades' => [$this->ana => '20']])->assertForbidden();
});

it('a manager sees every sheet and grades none', function () {
    $director = userWithRole($this->institution, 'Director');

    $this->actingAs($director)->get(tenantUrl($this->institution, '/notas'))->assertInertia(fn ($page) => $page->has('list.sheets', 2));
    $this->actingAs($director)->get(permissionSheetUrl())->assertOk()->assertInertia(fn ($page) => $page->where('sheet.canGrade', false));
    $this->actingAs($director)->put(tenantUrl($this->institution, "/notas/actividades/{$this->activity}"), ['grades' => [$this->ana => '20']])->assertForbidden();
});

it('a Representante, with ver-calificaciones, has no Notas entry and finds no sheet', function () {
    $guardian = userWithRole($this->institution, 'Representante');

    $this->actingAs($guardian)->get(tenantUrl($this->institution, '/notas'))->assertForbidden();
    $this->actingAs($guardian)->get(permissionSheetUrl())->assertNotFound();
    $this->actingAs($guardian)->put(tenantUrl($this->institution, "/notas/actividades/{$this->activity}"), ['grades' => [$this->ana => '20']])->assertForbidden();
});
