<?php

namespace App\Http\Controllers;

use AulaX\Enrollment\Application\RecordResult\RecordResultCommand;
use AulaX\Enrollment\Application\RecordResult\RecordResultHandler;
use AulaX\Enrollment\Application\Roster\PromotionRow;
use AulaX\Enrollment\Application\Roster\PromotionSheetQuery;
use AulaX\Enrollment\Domain\Outcome;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The results of the year, section by section (SPEC-010 MAT-R06): needs
 * gestionar-inscripciones (route middleware, and again in the use cases).
 */
class PromotionController extends Controller
{
    public function section(int $year, int $section, PromotionSheetQuery $sheet): Response
    {
        $view = $sheet($year, $section);

        return Inertia::render('Enrollment/Promotion', [
            'sheet' => [
                'schoolYearId' => $view->schoolYearId,
                'section' => [
                    'id' => $view->section->id,
                    'gradeName' => $view->section->gradeName,
                    'letter' => $view->section->letter,
                ],
                'ready' => $view->ready,
                'students' => array_map(fn (PromotionRow $row) => [
                    'enrollmentId' => $row->enrollmentId,
                    'fullName' => $row->fullName,
                    'identification' => $row->identification,
                    'outcome' => $row->outcome,
                    'pendingSubjects' => $row->pendingSubjects,
                    // SPEC-011 EVA-R08.4: the result proposed from the grades, to confirm.
                    'proposal' => $row->proposal === null ? null : [
                        'outcome' => $row->proposal->outcome,
                        'pendingSubjects' => $row->proposal->pendingSubjects,
                        'missing' => $row->proposal->missing,
                    ],
                ], $view->students),
                'outcomes' => $view->outcomes,
                'subjects' => $view->subjects,
            ],
            'outcomeLabels' => array_column(array_map(fn (Outcome $outcome) => [$outcome->value, $outcome->label()], Outcome::cases()), 1, 0),
        ]);
    }

    public function update(Request $request, int $enrollment, RecordResultHandler $recordResult): RedirectResponse
    {
        $data = $request->validate([
            'outcome' => ['nullable', 'string', 'max:24'],
            'pending_subjects' => ['nullable', 'array', 'max:10'],
            'pending_subjects.*' => ['integer'],
        ]);

        $recordResult(new RecordResultCommand(
            $enrollment,
            $data['outcome'] ?? null,
            array_map('intval', $data['pending_subjects'] ?? []),
            (int) $request->user()->getAuthIdentifier(),
        ));

        return back()->with('success', 'Se guardó el resultado.');
    }
}
