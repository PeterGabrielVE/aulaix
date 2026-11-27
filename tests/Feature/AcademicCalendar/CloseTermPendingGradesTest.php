<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;

require_once dirname(__DIR__, 2).'/Fixtures/Evaluation/evaluation_helpers.php';

/**
 * SPEC-011 EVA-R12.1 – R12.4 (HTTP): before closing a term, «Año escolar»
 * shows what is pending; closing it with something pending needs the
 * exception and its justification.
 */
beforeEach(function () {
    foreach (evaluationSetting() as $name => $value) {
        $this->{$name} = $value;
    }

    $this->director = userWithRole($this->institution, 'Director');
    $this->activity = evaluationActivityRow(evaluationPlanRow($this->year, $this->section, $this->math));
    activityGradeRow($this->activity, $this->ana);
});

function closeTermOverHttp(array $data = []): TestResponse
{
    $test = test();
    $calendar = tenantUrl($test->institution, '/calendario');

    return $test->actingAs($test->director)->from($calendar)->post(tenantUrl($test->institution, "/calendario/anios/{$test->year}/lapsos/cerrar"), ['confirmation' => true, ...$data]);
}

it('shows what is pending in the open term', function () {
    $this->actingAs($this->director)->get(tenantUrl($this->institution, '/calendario'))->assertOk()->assertInertia(fn ($page) => $page
        ->where('pendingGrades.term', 1)
        ->where('pendingGrades.total', 3)
        ->where('pendingGrades.rows.0', ['teacher' => 'Ana Rivas', 'section' => '3.er año A', 'subject' => 'Matemática', 'item' => 'Prueba', 'students' => 1])
        ->where('pendingGrades.rows.1.item', 'Sin plan de evaluación'));
});

it('closes with something pending only by exception, with a justification', function () {
    closeTermOverHttp()->assertSessionHasErrors(['term' => 'Hay 3 notas pendientes: complétalas o cierra por excepción con una justificación.']);
    closeTermOverHttp(['exception' => true, 'exception_justification' => ''])->assertSessionHasErrors(['exception_justification' => 'Indica la justificación para cerrar por excepción.']);

    expect(DB::table('terms')->where('number', 1)->value('status'))->toBe('open');

    closeTermOverHttp(['exception' => true, 'exception_justification' => 'Reposo médico del docente.'])->assertSessionHas('success', 'Se cerró el lapso.');

    expect(DB::table('terms')->where('number', 1)->value('status'))->toBe('closed')
        ->and(DB::table('activity_grades')->where('student_id', $this->luis)->value('not_presented'))->toBeTrue();
});
