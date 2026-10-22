<?php

namespace App\Http\Controllers;

use App\Http\Requests\StudentRequest;
use App\Http\Requests\WithdrawalRequest;
use AulaX\Catalogs\Application\Countries;
use AulaX\Catalogs\Application\Geography\GeographyDirectory;
use AulaX\Students\Application\ChangeHealth\ChangeHealthCommand;
use AulaX\Students\Application\ChangeHealth\ChangeHealthHandler;
use AulaX\Students\Application\ListStudents\ListStudentsQuery;
use AulaX\Students\Application\ListStudents\StudentListItem;
use AulaX\Students\Application\RegisterStudent\RegisterStudentCommand;
use AulaX\Students\Application\RegisterStudent\RegisterStudentHandler;
use AulaX\Students\Application\ReinstateStudent\ReinstateStudentHandler;
use AulaX\Students\Application\ShowStudent\GuardianLinkView;
use AulaX\Students\Application\ShowStudent\ShowStudentQuery;
use AulaX\Students\Application\ShowStudent\StudentView;
use AulaX\Students\Application\ShowStudent\WithdrawalView;
use AulaX\Students\Application\UpdateStudent\UpdateStudentCommand;
use AulaX\Students\Application\UpdateStudent\UpdateStudentHandler;
use AulaX\Students\Application\WithdrawStudent\WithdrawStudentCommand;
use AulaX\Students\Application\WithdrawStudent\WithdrawStudentHandler;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The students of the open institution (SPEC-009): list, record and form.
 * Seeing them needs ver-estudiantes and changing them gestionar-estudiantes
 * (route middleware, and again in the use cases); every rule lives in the
 * use cases.
 */
class StudentController extends Controller
{
    public function index(Request $request, ListStudentsQuery $listStudents): Response
    {
        $search = (string) $request->query('buscar', '');
        $withdrawn = $request->boolean('retirados');
        $page = $listStudents($search, $withdrawn, max(1, (int) $request->query('pagina', 1)));

        return Inertia::render('Students/Index', [
            'students' => array_map(fn (StudentListItem $student) => [
                'id' => $student->id,
                'fullName' => $student->fullName,
                'identificationType' => $student->identificationType,
                'identification' => $student->identification,
                'status' => $student->status,
            ], $page->items),
            'filters' => ['search' => $search, 'withdrawn' => $withdrawn],
            'pagination' => ['page' => $page->page, 'lastPage' => $page->lastPage(), 'total' => $page->total],
            'canManage' => $this->canManage($request),
        ]);
    }

    public function show(Request $request, int $student, ShowStudentQuery $showStudent): Response
    {
        return Inertia::render('Students/Show', [
            'student' => self::present($showStudent($student, includeHealth: true)),
            'canManage' => $this->canManage($request),
        ]);
    }

    public function create(Countries $countries, GeographyDirectory $geography): Response
    {
        return Inertia::render('Students/Form', [
            'student' => null,
            'options' => self::options($countries, $geography, null),
        ]);
    }

    public function edit(int $student, ShowStudentQuery $showStudent, Countries $countries, GeographyDirectory $geography): Response
    {
        $view = $showStudent($student, includeHealth: true);

        return Inertia::render('Students/Form', [
            'student' => self::present($view),
            'options' => self::options($countries, $geography, $view->parishId),
        ]);
    }

    public function store(StudentRequest $request, RegisterStudentHandler $registerStudent): RedirectResponse
    {
        $id = $registerStudent(new RegisterStudentCommand(
            $request->fields(),
            $request->validated('allergies'),
            $request->validated('medical_conditions'),
            $request->validated('disability'),
        ));

        return redirect()->route('students.show', $id->value)->with('success', 'Se registró el estudiante.');
    }

    public function update(StudentRequest $request, int $student, UpdateStudentHandler $updateStudent): RedirectResponse
    {
        $updateStudent(new UpdateStudentCommand($student, $request->fields()));

        return redirect()->route('students.show', $student)->with('success', 'Se guardaron los datos del estudiante.');
    }

    public function health(Request $request, int $student, ChangeHealthHandler $changeHealth): RedirectResponse
    {
        $data = $request->validate([
            'allergies' => ['nullable', 'string', 'max:1000'],
            'medical_conditions' => ['nullable', 'string', 'max:1000'],
            'disability' => ['nullable', 'string', 'max:1000'],
        ]);

        $changeHealth(new ChangeHealthCommand($student, $data['allergies'] ?? null, $data['medical_conditions'] ?? null, $data['disability'] ?? null));

        return back()->with('success', 'Se guardaron los datos de salud.');
    }

    public function withdraw(WithdrawalRequest $request, int $student, WithdrawStudentHandler $withdrawStudent): RedirectResponse
    {
        $withdrawStudent(new WithdrawStudentCommand(
            $student,
            $request->validated('withdrawn_on'),
            $request->validated('reason'),
            $request->validated('note'),
        ));

        return back()->with('success', 'Se registró el retiro.');
    }

    public function reinstate(int $student, ReinstateStudentHandler $reinstateStudent): RedirectResponse
    {
        $reinstateStudent($student);

        return back()->with('success', 'El estudiante está activo de nuevo.');
    }

    private function canManage(Request $request): bool
    {
        return (bool) $request->user()?->can('gestionar-estudiantes');
    }

    /**
     * @return array<string, mixed>
     */
    private static function present(StudentView $student): array
    {
        return [
            'id' => $student->id,
            'givenNames' => $student->givenNames,
            'familyNames' => $student->familyNames,
            'sex' => $student->sex,
            'birthDate' => $student->birthDate,
            'nationality' => $student->nationality,
            'identificationType' => $student->identificationType,
            'identification' => $student->identification,
            'previousSchoolIds' => $student->previousSchoolIds,
            'birthCountry' => $student->birthCountry,
            'birthStateId' => $student->birthStateId,
            'parishId' => $student->parishId,
            'address' => $student->address,
            'emergencyPhone' => $student->emergencyPhone,
            'status' => $student->status,
            'guardians' => array_map(fn (GuardianLinkView $guardian) => [
                'guardianId' => $guardian->guardianId,
                'givenNames' => $guardian->givenNames,
                'familyNames' => $guardian->familyNames,
                'documentType' => $guardian->documentType,
                'document' => $guardian->document,
                'phone' => $guardian->phone,
                'email' => $guardian->email,
                'address' => $guardian->address,
                'relationship' => $guardian->relationship,
                'isLegal' => $guardian->isLegal,
                'isPrimary' => $guardian->isPrimary,
                'hasAccount' => $guardian->hasAccount,
            ], $student->guardians),
            'withdrawals' => array_map(fn (WithdrawalView $withdrawal) => [
                'withdrawnOn' => $withdrawal->withdrawnOn,
                'reason' => $withdrawal->reason,
                'note' => $withdrawal->note,
                'reinstatedOn' => $withdrawal->reinstatedOn,
            ], $student->withdrawals),
            'health' => $student->health === null ? null : [
                'allergies' => $student->health->allergies,
                'medicalConditions' => $student->health->medicalConditions,
                'disability' => $student->health->disability,
            ],
            'hasHealthInfo' => $student->hasHealthInfo,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function options(Countries $countries, GeographyDirectory $geography, ?int $parishId): array
    {
        $location = $parishId === null ? null : $geography->locationOf($parishId);

        return [
            'countries' => $countries->all(),
            'states' => $geography->states(),
            'location' => $location,
            'municipalities' => $location === null ? [] : $geography->municipalitiesOf($location['state_id']),
            'parishes' => $location === null ? [] : $geography->parishesOf($location['municipality_id']),
        ];
    }
}
