<?php

namespace App\Http\Controllers;

use AulaX\Students\Application\Correction\GuardianCorrectionsQuery;
use AulaX\Students\Application\GuardianPortal\GuardianStudentsQuery;
use AulaX\Students\Application\GuardianPortal\ShowRepresentedStudentQuery;
use AulaX\Students\Application\ListStudents\StudentListItem;
use AulaX\Students\Application\ShowStudent\GuardianLinkView;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * «Mis representados» (SPEC-009 EST-R08.2, R08.3): what a guardian with an
 * account sees of its students. Only the basic record: no health data, and
 * of the other guardians only who they are to the student.
 */
class GuardianPortalController extends Controller
{
    public function index(Request $request, GuardianStudentsQuery $guardianStudents, GuardianCorrectionsQuery $guardianCorrections): Response
    {
        $account = (int) $request->user()->id;

        return Inertia::render('MyStudents/Index', [
            'students' => array_map(fn (StudentListItem $student) => [
                'id' => $student->id,
                'fullName' => $student->fullName,
                'identification' => $student->identification,
                'status' => $student->status,
            ], $guardianStudents($account)),
            'corrections' => array_map(CorrectionController::present(...), $guardianCorrections($account)),
        ]);
    }

    public function show(Request $request, int $student, ShowRepresentedStudentQuery $showStudent): Response
    {
        $view = $showStudent((int) $request->user()->id, $student);

        return Inertia::render('MyStudents/Show', [
            'student' => [
                'id' => $view->id,
                'givenNames' => $view->givenNames,
                'familyNames' => $view->familyNames,
                'sex' => $view->sex,
                'birthDate' => $view->birthDate,
                'nationality' => $view->nationality,
                'identificationType' => $view->identificationType,
                'identification' => $view->identification,
                'address' => $view->address,
                'emergencyPhone' => $view->emergencyPhone,
                'status' => $view->status,
                'guardians' => array_map(fn (GuardianLinkView $guardian) => [
                    'name' => "{$guardian->givenNames} {$guardian->familyNames}",
                    'relationship' => $guardian->relationship,
                    'isLegal' => $guardian->isLegal,
                ], $view->guardians),
            ],
        ]);
    }
}
