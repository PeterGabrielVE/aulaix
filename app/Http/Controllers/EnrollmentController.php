<?php

namespace App\Http\Controllers;

use App\Http\Requests\EnrollmentRequest;
use AulaX\Enrollment\Application\EnrollStudent\EnrollStudentCommand;
use AulaX\Enrollment\Application\EnrollStudent\EnrollStudentHandler;
use AulaX\Enrollment\Application\Roster\RosterStudent;
use AulaX\Enrollment\Application\Roster\SectionOverview;
use AulaX\Enrollment\Application\Roster\SectionRosterQuery;
use AulaX\Enrollment\Application\Roster\YearOverviewQuery;
use AulaX\Enrollment\Domain\AnnulmentReason;
use AulaX\Students\Application\Enrollable\EnrollableStudent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The enrollments of a school year (SPEC-010): seeing them needs
 * ver-estudiantes, enrolling gestionar-inscripciones (route middleware, and
 * again in the use cases). Every rule lives in the use cases.
 */
class EnrollmentController extends Controller
{
    public function index(Request $request, YearOverviewQuery $overview): Response
    {
        $year = $overview($request->integer('anio') ?: null);

        return Inertia::render('Enrollment/Index', [
            'overview' => [
                'schoolYearId' => $year->schoolYearId,
                'status' => $year->status,
                'years' => $year->years,
                'sections' => array_map(self::sectionData(...), $year->sections),
                'unenrolled' => array_map(fn (EnrollableStudent $student) => [
                    'id' => $student->id,
                    'fullName' => $student->fullName,
                    'identification' => $student->identification,
                ], $year->unenrolled),
            ],
            ...self::abilities($request),
        ]);
    }

    public function section(Request $request, int $year, int $section, SectionRosterQuery $roster, YearOverviewQuery $overview): Response
    {
        $view = $roster($year, $section);

        return Inertia::render('Enrollment/Section', [
            'roster' => [
                'schoolYearId' => $view->schoolYearId,
                'section' => self::sectionData($view->section),
                'students' => array_map(fn (RosterStudent $student) => [
                    'enrollmentId' => $student->enrollmentId,
                    'studentId' => $student->studentId,
                    'fullName' => $student->fullName,
                    'identification' => $student->identification,
                    'startsOn' => $student->startsOn,
                    'overCapacityReason' => $student->overCapacityReason,
                ], $view->students),
            ],
            'sections' => array_map(self::sectionData(...), $overview($year)->sections),
            'annulmentReasons' => array_map(fn (AnnulmentReason $reason) => ['value' => $reason->value, 'label' => $reason->label()], AnnulmentReason::cases()),
            ...self::abilities($request),
        ]);
    }

    public function store(EnrollmentRequest $request, EnrollStudentHandler $enroll): RedirectResponse
    {
        $result = $enroll(new EnrollStudentCommand(
            $request->integer('student'),
            $request->integer('school_year'),
            $request->integer('section'),
            $request->validated('date'),
            $request->validated('over_capacity_reason'),
            $request->validated('age_exception'),
            (int) $request->user()->getAuthIdentifier(),
        ));

        return back()->with('success', trim('Se inscribió al estudiante. '.$result->ageWarning));
    }

    /**
     * @return array<string, mixed>
     */
    private static function sectionData(SectionOverview $section): array
    {
        return [
            'id' => $section->id,
            'grade' => $section->grade,
            'gradeName' => $section->gradeName,
            'letter' => $section->letter,
            'capacity' => $section->capacity,
            'enrolled' => $section->enrolled,
            'overCapacity' => $section->overCapacity,
        ];
    }

    /**
     * @return array{canManage: bool, canApprove: bool}
     */
    private static function abilities(Request $request): array
    {
        return [
            'canManage' => (bool) $request->user()?->can('gestionar-inscripciones'),
            'canApprove' => (bool) $request->user()?->can('aprobar-excepciones-inscripcion'),
        ];
    }
}
