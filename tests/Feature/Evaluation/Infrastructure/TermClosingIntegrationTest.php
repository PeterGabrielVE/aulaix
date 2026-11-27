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

it('counts the pending groups', function () {
    $before = enrollmentYear(2025, 'closed');
    $owing = (int) DB::table('enrollments')->where('student_id', $this->luis)->value('id');
    $previous = enrollmentRow($this->luis, $before, enrollmentSection($before, 'media_2'), [
        'grade' => 'media_2', 'starts_on' => '2025-09-16', 'ends_on' => '2026-07-15', 'end_reason' => 'section_change',
        'outcome' => 'promoted_with_pending', 'outcome_recorded_at' => now(), 'outcome_recorded_by' => 1,
    ]);
    DB::table('enrollment_pending_subjects')->insert(['enrollment_id' => $previous, 'subject_id' => $this->math]);

    $view = app(PendingGradesQuery::class)($this->year, 1);

    // Luis owes Matemática of 2.º año, whose group has no plan yet.
    expect($owing)->toBeGreaterThan(0)
        ->and($view->total)->toBe(4)
        ->and(array_map(fn ($row) => [$row->section, $row->subject, $row->item, $row->students], array_slice($view->rows, -1)))
        ->toBe([['Pendientes de 2.º año', 'Matemática', 'Sin plan de evaluación', 1]]);
});
