<?php

namespace App\Http\Controllers;

use App\Http\Requests\PlacementChangeRequest;
use AulaX\Enrollment\Application\AnnulEnrollment\AnnulEnrollmentCommand;
use AulaX\Enrollment\Application\AnnulEnrollment\AnnulEnrollmentHandler;
use AulaX\Enrollment\Application\ChangePlacement\ChangeGradeHandler;
use AulaX\Enrollment\Application\ChangePlacement\ChangePlacementCommand;
use AulaX\Enrollment\Application\ChangePlacement\ChangeSectionHandler;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Changes to an enrollment during the year (SPEC-010 MAT-R04, R14): another
 * section, another grade (a directive's) or its annulment.
 */
class EnrollmentChangeController extends Controller
{
    public function section(PlacementChangeRequest $request, int $enrollment, ChangeSectionHandler $changeSection): RedirectResponse
    {
        $changeSection(self::command($request, $enrollment));

        return back()->with('success', 'Se cambió de sección.');
    }

    public function grade(PlacementChangeRequest $request, int $enrollment, ChangeGradeHandler $changeGrade): RedirectResponse
    {
        $changeGrade(self::command($request, $enrollment));

        return back()->with('success', 'Se cambió de grado.');
    }

    public function annul(Request $request, int $enrollment, AnnulEnrollmentHandler $annul): RedirectResponse
    {
        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:16'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $annul(new AnnulEnrollmentCommand($enrollment, $data['reason'] ?? null, $data['note'] ?? null, (int) $request->user()->getAuthIdentifier()));

        return back()->with('success', 'Se anuló la inscripción.');
    }

    private static function command(PlacementChangeRequest $request, int $enrollment): ChangePlacementCommand
    {
        return new ChangePlacementCommand(
            $enrollment,
            $request->integer('section'),
            $request->validated('date'),
            $request->validated('reason'),
            $request->validated('over_capacity_reason'),
            $request->validated('age_exception'),
            (int) $request->user()->getAuthIdentifier(),
        );
    }
}
