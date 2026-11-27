<?php

namespace App\Http\Controllers;

use App\Http\Requests\StudentMarkRequest;
use AulaX\Evaluation\Application\Marks\MarksRow;
use AulaX\Evaluation\Application\Marks\MarksSheetQuery;
use AulaX\Evaluation\Application\Marks\SaveFinalLiteralCommand;
use AulaX\Evaluation\Application\Marks\SaveFinalLiteralHandler;
use AulaX\Evaluation\Application\Marks\SaveLiteralCommand;
use AulaX\Evaluation\Application\Marks\SaveLiteralHandler;
use AulaX\Evaluation\Application\Marks\SaveReportCommand;
use AulaX\Evaluation\Application\Marks\SaveReportHandler;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The literals of Primaria and the reports of Inicial (SPEC-011): seeing
 * needs ver-calificaciones, saving gestionar-calificaciones (route
 * middleware); that the person guides the section is checked in the use
 * cases (EVA-R07.2).
 */
class MarksController extends Controller
{
    public function show(int $year, int $section, MarksSheetQuery $marks): Response
    {
        $view = $marks($year, $section);

        return Inertia::render('Grades/Marks', [
            'sheet' => [
                'schoolYearId' => $view->schoolYearId,
                'sectionId' => $view->section->id,
                'section' => "{$view->section->gradeName} {$view->section->letter}",
                'kind' => $view->kind,
                'canGrade' => $view->canGrade,
                'editable' => (object) $view->editable,
                'finalOpen' => $view->finalOpen,
                'rows' => array_map(fn (MarksRow $row) => [
                    'studentId' => $row->studentId,
                    'fullName' => $row->fullName,
                    'identification' => $row->identification,
                    'marks' => (object) $row->marks,
                ], $view->rows),
            ],
        ]);
    }

    public function update(
        StudentMarkRequest $request,
        int $year,
        int $section,
        int $student,
        SaveLiteralHandler $saveLiteral,
        SaveFinalLiteralHandler $saveFinalLiteral,
        SaveReportHandler $saveReport,
    ): RedirectResponse {
        $period = (string) $request->validated('period');
        $literal = $request->validated('literal');
        $description = $request->validated('description');

        if ($request->validated('kind') === 'report') {
            $saveReport(new SaveReportCommand($year, $section, $student, (int) $period, (string) $request->validated('report')));

            return back()->with('success', 'Se guardó el informe.');
        }

        if ($period === 'final') {
            $saveFinalLiteral(new SaveFinalLiteralCommand($year, $section, $student, $literal, $description));
        } else {
            $saveLiteral(new SaveLiteralCommand($year, $section, $student, (int) $period, $literal, $description));
        }

        return back()->with('success', 'Se guardó el literal.');
    }
}
