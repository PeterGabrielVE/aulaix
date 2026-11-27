<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;

require_once dirname(__DIR__, 2).'/Fixtures/Evaluation/evaluation_helpers.php';

/**
 * SPEC-011 EVA-R01, R02, R03, R06 (HTTP): «Notas», the sheet of a subject,
 * its plan and the grades of each activity, with the messages of each rule.
 */
beforeEach(function () {
    foreach (evaluationSetting() as $name => $value) {
        $this->{$name} = $value;
    }
});

function gradesUrl(string $path = ''): string
{
    return tenantUrl(test()->institution, '/notas'.$path);
}

function sheetUrl(?int $subject = null, int $term = 1): string
{
    $test = test();

    return gradesUrl("/{$test->year}/secciones/{$test->section}/materias/".($subject ?? $test->math)."/lapsos/{$term}");
}

function savePlanOverHttp(array $activities, array $data = []): TestResponse
{
    $test = test();

    return $test->actingAs($test->teacher)->from(sheetUrl())->put(sheetUrl().'/plan', ['activities' => $activities, ...$data]);
}

function saveGradesOverHttp(int $activity, array $grades): TestResponse
{
    $test = test();

    return $test->actingAs($test->teacher)->from(sheetUrl())->put(gradesUrl("/actividades/{$activity}"), ['grades' => $grades]);
}

it('lists the sheets of a teacher, opening on the open term', function () {
    $this->actingAs($this->teacher)->get(gradesUrl())->assertOk()->assertInertia(fn ($page) => $page
        ->component('Grades/Index')
        ->where('list.schoolYearId', $this->year)
        ->where('list.term', 1)
        ->has('list.sheets', 1)
        ->where('list.sheets.0.section', '3.er año A')
        ->where('list.sheets.0.subject', 'Matemática')
        ->where('list.sheets.0.teaches', true));
});

it('saves a plan and shows it in the sheet, with the students of the section', function () {
    savePlanOverHttp([
        ['name' => 'Prueba', 'date' => '2026-10-05', 'weight' => '60'],
        ['name' => 'Taller', 'date' => '2026-10-12', 'weight' => '40'],
    ])->assertRedirect(sheetUrl())->assertSessionHas('success', 'Se guardó el plan de evaluación.');

    $this->actingAs($this->teacher)->get(sheetUrl())->assertOk()->assertInertia(fn ($page) => $page
        ->component('Grades/Sheet')
        ->where('sheet.section', '3.er año A')
        ->where('sheet.subject', 'Matemática')
        ->where('sheet.term', 1)
        ->where('sheet.canGrade', true)
        ->where('sheet.editable', true)
        ->where('sheet.activities.0.name', 'Prueba')
        ->where('sheet.activities.0.weight', '60')
        ->where('sheet.rows.0.fullName', 'Álvarez, Luis')
        ->where('sheet.rows.1.fullName', 'Pérez, Ana')
        ->where('sheet.rows.1.termState', 'incomplete'));
});

it('says what is wrong with a plan', function () {
    savePlanOverHttp([['name' => 'Prueba', 'date' => '2026-10-05', 'weight' => '60']])
        ->assertSessionHasErrors('activities');
    savePlanOverHttp([['name' => 'Prueba', 'date' => '2027-02-01', 'weight' => '100']])
        ->assertSessionHasErrors('activities');
    savePlanOverHttp(array_fill(0, 31, ['name' => 'x', 'date' => '2026-10-05', 'weight' => '1']))
        ->assertSessionHasErrors('activities');

    expect(DB::table('evaluation_activities')->count())->toBe(0);
});

it('saves the grades of an activity, with NP, and the grade of the term', function () {
    savePlanOverHttp([['name' => 'Prueba', 'date' => '2026-10-05', 'weight' => '100']]);
    $activity = (int) DB::table('evaluation_activities')->value('id');

    saveGradesOverHttp($activity, [$this->ana => '15,5', $this->luis => 'NP'])
        ->assertRedirect(sheetUrl())->assertSessionHas('success', 'Se guardaron las notas.');

    $this->actingAs($this->teacher)->get(sheetUrl())->assertInertia(fn ($page) => $page
        ->where("sheet.rows.1.marks.{$activity}", '15,5')
        ->where('sheet.rows.1.termScore', 16)
        ->where("sheet.rows.0.marks.{$activity}", 'NP')
        ->where('sheet.rows.0.termScore', 1));
});

it('says what is wrong with a grade, and takes at most 60 at a time', function () {
    savePlanOverHttp([['name' => 'Prueba', 'date' => '2026-10-05', 'weight' => '100']]);
    $activity = (int) DB::table('evaluation_activities')->value('id');

    saveGradesOverHttp($activity, [$this->ana => '21'])->assertSessionHasErrors(["grades.{$this->ana}" => 'La nota debe estar entre 1 y 20, con hasta dos decimales.']);
    saveGradesOverHttp($activity, [enrollmentStudent('Mora', 'Eva') => '10'])->assertSessionHasErrors('grades.*');
    saveGradesOverHttp($activity, array_fill_keys(range(1, 61), '10'))->assertSessionHasErrors('grades');

    expect(DB::table('activity_grades')->count())->toBe(0);
});

it('keeps the grades of a closed term', function () {
    savePlanOverHttp([['name' => 'Prueba', 'date' => '2026-10-05', 'weight' => '100']]);
    $activity = (int) DB::table('evaluation_activities')->value('id');
    DB::table('terms')->where('number', 1)->update(['status' => 'closed']);
    DB::table('terms')->where('number', 2)->update(['status' => 'open']);

    saveGradesOverHttp($activity, [$this->ana => '15'])->assertSessionHasErrors('term');

    $this->actingAs($this->teacher)->get(sheetUrl())->assertInertia(fn ($page) => $page->where('sheet.editable', false));
});

it('does not find a sheet of a subject out of the curriculum or a term that does not exist', function () {
    $other = evaluationSubject('Química');

    $this->actingAs($this->teacher)->get(sheetUrl($other))->assertNotFound();
    $this->actingAs($this->teacher)->get(sheetUrl(term: 4))->assertNotFound();
});
