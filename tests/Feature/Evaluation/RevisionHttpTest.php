<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;

require_once dirname(__DIR__, 2).'/Fixtures/Evaluation/evaluation_helpers.php';

/**
 * SPEC-016 RVP-R01, R02.5 (HTTP): with the third term closed, the sheet of
 * a subject takes the revisions of its failed final grades and shows the
 * final grade that counts.
 */
beforeEach(function () {
    foreach (evaluationSetting() as $name => $value) {
        $this->{$name} = $value;
    }

    // Ana passes Matemática with 15; Luis fails it with 8.
    foreach ([1 => '2026-10-05', 2 => '2027-02-01', 3 => '2027-05-03'] as $term => $date) {
        $activity = evaluationActivityRow(evaluationPlanRow($this->year, $this->section, $this->math, $term), ['date' => $date]);
        activityGradeRow($activity, $this->ana, ['score' => 1500]);
        activityGradeRow($activity, $this->luis, ['score' => 800]);
    }

    DB::table('terms')->update(['status' => 'closed']);
});

function revisionSheetUrl(int $term = 3): string
{
    $test = test();

    return tenantUrl($test->institution, "/notas/{$test->year}/secciones/{$test->section}/materias/{$test->math}/lapsos/{$term}");
}

function saveRevisionsOverHttp(array $revisions, ?object $user = null): TestResponse
{
    $test = test();

    return $test->actingAs($user ?? $test->teacher)->from(revisionSheetUrl())
        ->put(tenantUrl($test->institution, "/notas/{$test->year}/secciones/{$test->section}/materias/{$test->math}/revisiones"), ['revisions' => $revisions]);
}

it('shows the failed final grades waiting for their revision', function () {
    $this->actingAs($this->teacher)->get(revisionSheetUrl())->assertOk()->assertInertia(fn ($page) => $page
        ->where('sheet.revisionsOpen', true)
        ->where('sheet.rows.0.fullName', 'Álvarez, Luis')
        ->where('sheet.rows.0.finalScore', 8)
        ->where('sheet.rows.0.revision', null)
        ->where('sheet.rows.0.decided', ['state' => 'needs_revision', 'score' => null])
        ->where('sheet.rows.1.decided', ['state' => 'approved', 'score' => 15]));
});

it('records a revision and shows the final grade that counts', function () {
    saveRevisionsOverHttp([$this->luis => '11,5'])->assertRedirect(revisionSheetUrl())->assertSessionHas('success', 'Se guardaron las revisiones.');

    $this->actingAs($this->teacher)->get(revisionSheetUrl())->assertInertia(fn ($page) => $page
        ->where('sheet.rows.0.revision', '11,5')
        ->where('sheet.rows.0.decided', ['state' => 'approved', 'score' => 12]));
});

it('says what is wrong with a revision', function () {
    saveRevisionsOverHttp([$this->luis => '25'])->assertSessionHasErrors(["revisions.{$this->luis}" => 'La nota debe estar entre 1 y 20, con hasta dos decimales.']);
    saveRevisionsOverHttp([$this->ana => '12'])->assertSessionHasErrors(["revisions.{$this->ana}" => 'La definitiva no está reprobada: no lleva revisión.']);
    saveRevisionsOverHttp(array_fill_keys(range(1, 61), '10'))->assertSessionHasErrors('revisions');

    DB::table('terms')->where('number', 3)->update(['status' => 'open']);

    saveRevisionsOverHttp([$this->luis => '12'])->assertSessionHasErrors(['term' => 'La revisión se registra con el tercer lapso cerrado.']);

    expect(DB::table('grade_revisions')->count())->toBe(0);
});

it('only the teacher of the subject records its revisions', function () {
    $other = userWithRole($this->institution, 'Docente');
    DB::table('teaching_assignments')->insert(['section_id' => $this->section, 'subject_id' => $this->physics, 'account_id' => $other->id]);

    saveRevisionsOverHttp([$this->luis => '12'], $other)->assertForbidden();
    saveRevisionsOverHttp([$this->luis => '12'], userWithRole($this->institution, 'Director'))->assertForbidden();

    expect(DB::table('grade_revisions')->count())->toBe(0);
});
