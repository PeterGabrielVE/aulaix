<?php

use AulaX\AcademicCalendar\Application\CloseTerm\CloseTermHandler;
use AulaX\Evaluation\Application\Pending\PendingGradesQuery;
use AulaX\Shared\Domain\BusinessRuleViolation;
use Illuminate\Support\Facades\DB;

require_once dirname(__DIR__, 3).'/Fixtures/Evaluation/evaluation_helpers.php';

/**
 * SPEC-011 EVA-R12.1 – R12.4 with the real modules: the calendar does not
 * close a term with grades pending; by exception it fills them with NP and
 * records the justification, all in the closing's transaction.
 */
beforeEach(function () {
    foreach (evaluationSetting() as $name => $value) {
        $this->{$name} = $value;
    }

    $this->actingAs(userWithRole($this->institution, 'Director'));
    $this->activity = evaluationActivityRow(evaluationPlanRow($this->year, $this->section, $this->math));
    activityGradeRow($this->activity, $this->ana);
});

function firstTermState(): string
{
    return DB::table('terms')->where('school_year_id', test()->year)->where('number', 1)->value('status');
}

it('lists what is pending with who teaches it', function () {
    $view = app(PendingGradesQuery::class)($this->year, 1);

    expect($view->total)->toBe(3)
        ->and(array_map(fn ($row) => [$row->teacher, $row->subject, $row->item, $row->students], $view->rows))->toBe([
            ['Ana Rivas', 'Matemática', 'Prueba', 1],
            [null, 'Física', 'Sin plan de evaluación', 2],
        ]);
});

it('does not close the term with grades pending', function () {
    expect(fn () => app(CloseTermHandler::class)($this->year, true))
        ->toThrow(BusinessRuleViolation::class, 'Hay 3 notas pendientes: complétalas o cierra por excepción con una justificación.')
        ->and(firstTermState())->toBe('open');
});

it('closes by exception, with NP in the missing grades and the justification in the audit log', function () {
    app(CloseTermHandler::class)($this->year, true, 'Reposo médico del docente.');

    expect(firstTermState())->toBe('closed')
        ->and(DB::table('activity_grades')->where('student_id', $this->luis)->value('not_presented'))->toBeTrue()
        ->and(DB::table('audit_entries')->where('event_type', 'evaluation.term.closed_by_exception')->value('changes'))
        ->toContain('Reposo médico del docente.');
});
