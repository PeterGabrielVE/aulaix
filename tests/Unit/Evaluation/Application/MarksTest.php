<?php

use AulaX\Evaluation\Application\EvaluationNotFound;
use AulaX\Evaluation\Application\Marks\MarksSheetQuery;
use AulaX\Evaluation\Application\Marks\MarksSheetView;
use AulaX\Evaluation\Application\Marks\SaveFinalLiteralCommand;
use AulaX\Evaluation\Application\Marks\SaveFinalLiteralHandler;
use AulaX\Evaluation\Application\Marks\SaveLiteralCommand;
use AulaX\Evaluation\Application\Marks\SaveLiteralHandler;
use AulaX\Evaluation\Application\Marks\SaveReportCommand;
use AulaX\Evaluation\Application\Marks\SaveReportHandler;
use AulaX\Evaluation\Domain\Literal;
use AulaX\Evaluation\Domain\MarkKind;
use AulaX\Evaluation\Domain\MarkNotValid;
use AulaX\Evaluation\Domain\NotEditable;
use AulaX\Shared\Domain\AuthorizationDenied;
use Tests\Fixtures\Evaluation\EvaluationScene;

/**
 * SPEC-011 EVA-R04.1 – R04.5, R05.1 – R05.3, R06.1, R07.2 – R07.6, R10.1,
 * R10.2: the guide of a section of Primaria records the literals and of
 * Inicial the reports, and whoever may see them sees the sheet.
 */
beforeEach(function () {
    $this->scene = new EvaluationScene;
    $this->scene->teaching->guiding = [[9, 20], [9, 30]];
    $this->scene->enrollments->enroll(1, 20, 'primaria_1', name: 'Pérez, Ana');
    $this->scene->enrollments->enroll(2, 20, 'primaria_1', until: '2026-10-10', name: 'Rojas, Paz');
    $this->scene->enrollments->enroll(3, 30, 'preescolar_2', name: 'Mora, Eva');
});

function saveLiteral(int $term = 1, string $literal = 'B', ?string $description = 'Lee con fluidez.', int $student = 1, int $section = 20): void
{
    $scene = test()->scene;
    (new SaveLiteralHandler($scene->store, $scene->teaching, $scene->calendar, $scene->enrollments, $scene->permissions, $scene->clock, $scene->audit, $scene->transactions))(
        new SaveLiteralCommand(2, $section, $student, $term, $literal, $description),
    );
}

function saveFinalLiteral(string $literal = 'A'): void
{
    $scene = test()->scene;
    (new SaveFinalLiteralHandler($scene->store, $scene->teaching, $scene->calendar, $scene->enrollments, $scene->permissions, $scene->clock, $scene->audit, $scene->transactions))(
        new SaveFinalLiteralCommand(2, 20, 1, $literal, null),
    );
}

function saveReport(string $text = 'Juega con sus compañeros.', int $section = 30, int $student = 3): void
{
    $scene = test()->scene;
    (new SaveReportHandler($scene->store, $scene->teaching, $scene->calendar, $scene->enrollments, $scene->permissions, $scene->clock, $scene->audit, $scene->transactions))(
        new SaveReportCommand(2, $section, $student, 1, $text),
    );
}

function marksSheet(int $section = 20): MarksSheetView
{
    $scene = test()->scene;

    return (new MarksSheetQuery($scene->store, $scene->teaching, $scene->calendar, $scene->enrollments, $scene->permissions))(2, $section);
}

it('the guide records the literal of a term with its description, recorded with what changed', function () {
    saveLiteral();

    expect($this->scene->store->marks[1]->markOf('1', MarkKind::Literal)?->literal)->toBe(Literal::B)
        ->and($this->scene->audit->types())->toBe(['evaluation.student.marks_saved'])
        ->and($this->scene->audit->changesOf(0))->toBe(['literal_1' => ['from' => null, 'to' => 'B'], 'description_1' => ['from' => null, 'to' => 'escrita']])
        ->and($this->scene->audit->recorded[0]['inTransaction'])->toBeTrue();

    saveLiteral();

    expect($this->scene->audit->types())->toHaveCount(1);
});

it('refuses a literal out of A – E, a student no longer in the section, and a section that is not of Primaria', function () {
    expect(fn () => saveLiteral(literal: 'F'))->toThrow(MarkNotValid::class, 'El literal debe ser A, B, C, D o E.')
        ->and(fn () => saveLiteral(student: 2))->toThrow(MarkNotValid::class, 'El estudiante no tiene inscripción vigente en la sección.')
        ->and(fn () => saveLiteral(student: 3, section: 30))->toThrow(MarkNotValid::class, 'Los literales son de Primaria.')
        ->and(fn () => saveReport(section: 20, student: 1))->toThrow(MarkNotValid::class, 'Los informes son de Educación Inicial.');
});

it('only the guide records literals and reports', function () {
    $this->scene->teaching->guiding = [];

    expect(fn () => saveLiteral())->toThrow(AuthorizationDenied::class, 'No eres el guía de esa sección.')
        ->and(fn () => saveReport())->toThrow(AuthorizationDenied::class);

    $this->scene->teaching->guiding = [[9, 20]];
    $this->scene->permissions->grade = false;

    expect(fn () => saveLiteral())->toThrow(AuthorizationDenied::class);
});

it('takes the literal of a term only while the term takes changes', function () {
    $this->scene->calendar->terms[1]['status'] = 'closed';

    expect(fn () => saveLiteral())->toThrow(NotEditable::class, 'El lapso está cerrado: sus notas no cambian.')
        ->and(fn () => saveLiteral(term: 2))->toThrow(NotEditable::class, 'El lapso aún no se abre.');

    $this->scene->permissions->editClosedTerms = true;
    saveLiteral();

    expect($this->scene->audit->changesOf(0))->toHaveKey('closed_term');
});

it('records the final literal once the third term is closed, never in a closed year', function () {
    expect(fn () => saveFinalLiteral())->toThrow(MarkNotValid::class, 'El literal final se registra con el tercer lapso cerrado.');

    $this->scene->calendar->terms = array_map(fn (array $term) => [...$term, 'status' => 'closed'], $this->scene->calendar->terms);
    saveFinalLiteral();

    expect($this->scene->store->marks[1]->markOf('final', MarkKind::Literal)?->literal)->toBe(Literal::A);

    $this->scene->calendar->yearClosed = true;

    expect(fn () => saveFinalLiteral('B'))->toThrow(NotEditable::class, 'El año escolar está cerrado: sus notas no cambian.');
});

it('records the report of a student of Inicial, never its text in the audit log', function () {
    saveReport();

    expect($this->scene->store->marks[3]->markOf('1', MarkKind::Report)?->description)->toBe('Juega con sus compañeros.')
        ->and($this->scene->audit->changesOf(0))->toBe(['report_1' => ['from' => null, 'to' => 'escrito']])
        ->and(fn () => saveReport(''))->toThrow(MarkNotValid::class, 'El informe debe tener entre 1 y 5000 caracteres.');
});

it('shows the guide the literals of its section, with what each term takes', function () {
    saveLiteral();
    $sheet = marksSheet();

    expect($sheet->kind)->toBe('literal')
        ->and($sheet->canGrade)->toBeTrue()
        ->and($sheet->editable)->toBe([1 => true, 2 => false, 3 => false])
        ->and($sheet->finalOpen)->toBeFalse()
        ->and(array_map(fn ($row) => [$row->fullName, $row->marks['1']], $sheet->rows))->toBe([
            ['Pérez, Ana', ['literal' => 'B', 'description' => 'Lee con fluidez.', 'incomplete' => false]],
        ]);
});

it('shows the sheet to a coordinator of the grade and to a manager, and to nobody else', function () {
    $this->scene->permissions->accountId = 8;

    expect(fn () => marksSheet())->toThrow(EvaluationNotFound::class);

    $this->scene->teaching->coordinated[8] = ['primaria_1'];

    expect(marksSheet()->canGrade)->toBeFalse();

    $this->scene->teaching->coordinated[8] = [];
    $this->scene->permissions->seesAll = true;

    expect(marksSheet(30)->kind)->toBe('report')
        ->and(fn () => marksSheet(10))->toThrow(EvaluationNotFound::class);
});
