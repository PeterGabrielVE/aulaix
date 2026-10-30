<?php

namespace App\Http\Controllers;

use App\Http\Requests\ActivityGradesRequest;
use App\Http\Requests\EvaluationPlanRequest;
use App\Http\Requests\PendingTeacherRequest;
use App\Http\Requests\RevisionsRequest;
use AulaX\AcademicCalendar\Application\SchoolYearLookup;
use AulaX\Evaluation\Application\Gradebook\ActivityView;
use AulaX\Evaluation\Application\PendingGroups\AssignPendingTeacherCommand;
use AulaX\Evaluation\Application\PendingGroups\AssignPendingTeacherHandler;
use AulaX\Evaluation\Application\PendingGroups\PendingGroupEntry;
use AulaX\Evaluation\Application\PendingGroups\PendingGroupRow;
use AulaX\Evaluation\Application\PendingGroups\PendingGroupSheetQuery;
use AulaX\Evaluation\Application\PendingGroups\PendingGroupsQuery;
use AulaX\Evaluation\Application\PendingGroups\SaveGroupGradesHandler;
use AulaX\Evaluation\Application\PendingGroups\SaveGroupPlanCommand;
use AulaX\Evaluation\Application\PendingGroups\SaveGroupPlanHandler;
use AulaX\Evaluation\Application\PendingGroups\SaveGroupRevisionHandler;
use AulaX\Evaluation\Domain\ActivityInput;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The groups of pending subjects (SPEC-016): listing them and assigning
 * their teacher needs gestionar-estructura; their sheet, ver-calificaciones;
 * planning, grading and revising, gestionar-calificaciones (route
 * middleware). Who teaches, coordinates or manages each group is checked
 * again in the use cases (RVP-NF01).
 */
class PendingGroupController extends Controller
{
    public function index(PendingGroupsQuery $groups, SchoolYearLookup $years): Response
    {
        $year = $years->activeYear();

        return Inertia::render('Grades/PendingGroups', [
            'schoolYearId' => $year,
            'groups' => $year === null ? [] : array_map(fn (PendingGroupEntry $group) => [
                'groupId' => $group->groupId,
                'subjectId' => $group->subjectId,
                'subject' => $group->subject,
                'originGradeCode' => $group->originGrade,
                'originGrade' => $group->originGradeName,
                'teacherAccountId' => $group->teacherAccountId,
                'teacher' => $group->teacher,
                'members' => $group->members,
            ], $groups($year)),
            'teachers' => $groups->teachers(),
        ]);
    }

    public function teacher(PendingTeacherRequest $request, SchoolYearLookup $years, AssignPendingTeacherHandler $assign): RedirectResponse
    {
        $assign(new AssignPendingTeacherCommand(
            (int) $years->activeYear(),
            $request->integer('subject'),
            (string) $request->validated('origin_grade'),
            $request->validated('teacher') === null ? null : $request->integer('teacher'),
        ));

        return back()->with('success', 'Se asignó el docente.');
    }

    public function sheet(int $group, int $term, PendingGroupSheetQuery $sheet): Response
    {
        $view = $sheet($group, $term);

        // The same screen as the sheet of a section (SPEC-011), with the URLs of the group.
        return Inertia::render('Grades/Sheet', [
            'sheet' => [
                'schoolYearId' => $view->schoolYearId,
                'sectionId' => null,
                'section' => "Pendientes de {$view->originGradeName}",
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
                'rows' => array_map(fn (PendingGroupRow $row) => [
                    'studentId' => $row->studentId,
                    'fullName' => $row->fullName,
                    'identification' => $row->identification,
                    'marks' => (object) $row->marks,
                    'termState' => $row->termState,
                    'termScore' => $row->termScore,
                    'finalState' => $row->finalState,
                    'finalScore' => $row->finalScore,
                    'failed' => $row->failed,
                    'revision' => $row->revision,
                    'decided' => ['state' => $row->decided->state, 'score' => $row->decided->score],
                    'standing' => $row->standing,
                ], $view->rows),
                'revisionsOpen' => $view->revisionsOpen,
                'urls' => [
                    'plan' => route('grades.pending.plan', ['group' => $group, 'term' => $term]),
                    'grades' => route('grades.pending.activity', ['activity' => '__activity__']),
                    'revisions' => route('grades.pending.revisions', ['group' => $group]),
                    'terms' => array_combine([1, 2, 3], array_map(fn (int $number) => route('grades.pending.sheet', ['group' => $group, 'term' => $number]), [1, 2, 3])),
                ],
            ],
        ]);
    }

    public function plan(EvaluationPlanRequest $request, int $group, int $term, SaveGroupPlanHandler $savePlan): RedirectResponse
    {
        $savePlan(new SaveGroupPlanCommand(
            $group,
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

    public function grades(ActivityGradesRequest $request, int $activity, SaveGroupGradesHandler $saveGrades): RedirectResponse
    {
        $saveGrades($activity, $request->validated('grades'));

        return back()->with('success', 'Se guardaron las notas.');
    }

    public function revisions(RevisionsRequest $request, int $group, SaveGroupRevisionHandler $saveRevisions): RedirectResponse
    {
        $saveRevisions($group, $request->validated('revisions'));

        return back()->with('success', 'Se guardaron las revisiones.');
    }
}
