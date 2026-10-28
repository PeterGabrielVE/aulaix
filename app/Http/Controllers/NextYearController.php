<?php

namespace App\Http\Controllers;

use AulaX\Enrollment\Application\NextYear\EnrollNextYearCommand;
use AulaX\Enrollment\Application\NextYear\EnrollNextYearHandler;
use AulaX\Enrollment\Application\NextYear\ExcludedStudent;
use AulaX\Enrollment\Application\NextYear\ProposalEntry;
use AulaX\Enrollment\Application\NextYear\ProposeNextYearQuery;
use AulaX\Enrollment\Application\Roster\SectionOverview;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Enrolling a year's students in the next one by their results (SPEC-010
 * MAT-R07): a proposal, then its confirmation. Needs
 * gestionar-inscripciones (route middleware, and again in the use cases).
 */
class NextYearController extends Controller
{
    public function show(int $year, ProposeNextYearQuery $propose): Response
    {
        $proposal = $propose($year);

        return Inertia::render('Enrollment/NextYear', [
            'proposal' => [
                'fromSchoolYearId' => $proposal->fromSchoolYearId,
                'toSchoolYearId' => $proposal->toSchoolYearId,
                'entries' => array_map(fn (ProposalEntry $entry) => [
                    'studentId' => $entry->studentId,
                    'fullName' => $entry->fullName,
                    'outcome' => $entry->outcome,
                    'toGrade' => $entry->toGrade,
                    'toGradeName' => $entry->toGradeName,
                    'sectionIds' => $entry->sectionIds,
                ], $proposal->entries),
                'excluded' => array_map(fn (ExcludedStudent $student) => [
                    'studentId' => $student->studentId,
                    'fullName' => $student->fullName,
                    'reason' => $student->reason,
                ], $proposal->excluded),
                'sections' => array_map(fn (SectionOverview $section) => [
                    'id' => $section->id,
                    'grade' => $section->grade,
                    'gradeName' => $section->gradeName,
                    'letter' => $section->letter,
                    'capacity' => $section->capacity,
                    'enrolled' => $section->enrolled,
                ], $proposal->sections),
            ],
        ]);
    }

    public function store(Request $request, int $year, EnrollNextYearHandler $enrollNextYear, ProposeNextYearQuery $propose): RedirectResponse
    {
        $data = $request->validate([
            'placements' => ['required', 'array', 'max:2000'],
            'placements.*' => ['integer'],
            'over_capacity_reasons' => ['nullable', 'array'],
            'over_capacity_reasons.*' => ['nullable', 'string', 'max:500'],
        ]);

        $count = $enrollNextYear(new EnrollNextYearCommand(
            $year,
            array_map('intval', $data['placements']),
            array_filter($data['over_capacity_reasons'] ?? [], fn (?string $reason) => $reason !== null && $reason !== ''),
            (int) $request->user()->getAuthIdentifier(),
        ));

        return redirect()->route('enrollments.index', ['anio' => $propose($year)->toSchoolYearId])
            ->with('success', "Se inscribieron {$count} estudiantes en el año siguiente.");
    }
}
