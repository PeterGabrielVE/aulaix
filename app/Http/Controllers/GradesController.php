<?php

namespace App\Http\Controllers;

use App\Http\Requests\ActivityGradesRequest;
use App\Http\Requests\EvaluationPlanRequest;
use AulaX\Evaluation\Application\Gradebook\ActivityView;
use AulaX\Evaluation\Application\Gradebook\GradebookQuery;
use AulaX\Evaluation\Application\Gradebook\GradebookRow;
use AulaX\Evaluation\Application\PendingGroups\AssignedPendingGroupsQuery;
use AulaX\Evaluation\Application\SaveActivityGrades\SaveActivityGradesHandler;
use AulaX\Evaluation\Application\SavePlan\SavePlanCommand;
use AulaX\Evaluation\Application\SavePlan\SavePlanHandler;
use AulaX\Evaluation\Application\Sheets\SectionEntry;
use AulaX\Evaluation\Application\Sheets\SheetEntry;
use AulaX\Evaluation\Application\Sheets\SheetsQuery;
use AulaX\Evaluation\Domain\ActivityInput;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * «Notas» (SPEC-011): seeing needs ver-calificaciones, saving
 * gestionar-calificaciones (route middleware); who teaches, coordinates or
 * manages what is checked again in the use cases (EVA-R07, NF01).
 */
class GradesController extends Controller
{
    public function index(Request $request, SheetsQuery $sheets, AssignedPendingGroupsQuery $pendingGroups): Response
    {
        $list = $sheets();

        return Inertia::render('Grades/Index', [
            'list' => [
                'schoolYearId' => $list->schoolYearId,
                'term' => $list->term,
                'sheets' => array_map(fn (SheetEntry $sheet) => [
                    'sectionId' => $sheet->sectionId,
                    'section' => $sheet->section,
                    'subjectId' => $sheet->subjectId,
                    'subject' => $sheet->subject,
                    'teaches' => $sheet->teaches,
                ], $list->sheets),
                'sections' => array_map(fn (SectionEntry $section) => [
                    'sectionId' => $section->sectionId,
                    'section' => $section->section,
                    'kind' => $section->kind,
                    'guides' => $section->guides,
                ], $list->sections),
                // SPEC-016: the groups of pending subjects assigned to the person.
                'pendingGroups' => $pendingGroups(),
                'managesPendingGroups' => (bool) $request->user()?->can('gestionar-estructura'),
            ],
        ]);
    }

    public function sheet(int $year, int $section, int $subject, int $term, GradebookQuery $gradebook): Response
    {
        $view = $gradebook($year, $section, $subject, $term);

        return Inertia::render('Grades/Sheet', [
            'sheet' => [
                'schoolYearId' => $view->schoolYearId,
                'sectionId' => $view->section->id,
                'section' => "{$view->section->gradeName} {$view->section->letter}",
                'subjectId' => $view->subjectId,
                'subject' => $view->subject,
                'term' => $view->term,
                'canGrade' => $view->canGrade,
                'editable' => $view->editable,
                'activities' => array_map(fn (ActivityView $activity) => [
                    'id' => $activity->id,
                    'name' => $activity->name,
                    'date' => $activity->date,
                    'weight' => $activity->weight,
                ], $view->activities),
                'rows' => array_map(fn (GradebookRow $row) => [
                    'studentId' => $row->studentId,
                    'fullName' => $row->fullName,
                    'identification' => $row->identification,
                    'marks' => (object) $row->marks,
                    'termState' => $row->termState,
                    'termScore' => $row->termScore,
                    'finalState' => $row->finalState,
                    'finalScore' => $row->finalScore,
                    'failed' => $row->failed,
                    // SPEC-016 RVP-R02.5: the revision and the final grade that counts.
                    'revision' => $row->revision,
                    'decided' => $row->decided === null ? null : ['state' => $row->decided->state, 'score' => $row->decided->score],
                ], $view->rows),
                'revisionsOpen' => $view->revisionsOpen,
            ],
        ]);
    }

    public function plan(EvaluationPlanRequest $request, int $year, int $section, int $subject, int $term, SavePlanHandler $savePlan): RedirectResponse
    {
        $savePlan(new SavePlanCommand(
            $year,
            $section,
            $subject,
            $term,
            array_map(fn (array $activity) => new ActivityInput(
                isset($activity['id']) ? (int) $activity['id'] : null,
                $activity['name'] ?? null,
                $activity['date'] ?? null,
                $activity['weight'] ?? null,
            ), array_values($request->validated('activities'))),
            $request->boolean('confirm_removals'),
        ));

        return back()->with('success', 'Se guardó el plan de evaluación.');
    }

    public function grades(ActivityGradesRequest $request, int $activity, SaveActivityGradesHandler $saveGrades): RedirectResponse
    {
        $saveGrades($activity, $request->validated('grades'));

        return back()->with('success', 'Se guardaron las notas.');
    }
}
