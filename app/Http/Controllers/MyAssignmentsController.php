<?php

namespace App\Http\Controllers;

use AulaX\AcademicStructure\Application\MyAssignments\MyAssignmentsQuery;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * «Mis asignaciones» (SPEC-008 EAC-R08.2): a teacher's own classes in the
 * active school year. Every member sees only their own.
 */
class MyAssignmentsController extends Controller
{
    public function __invoke(Request $request, MyAssignmentsQuery $myAssignments): Response
    {
        $view = $myAssignments((int) $request->user()->id);

        return Inertia::render('MyAssignments/Index', [
            'assignments' => [
                'schoolYearId' => $view->schoolYearId,
                'teaching' => $view->teaching,
                'guiding' => $view->guiding,
            ],
        ]);
    }
}
