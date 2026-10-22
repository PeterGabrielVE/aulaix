<?php

use AulaX\Students\Application\FindGuardian\FindGuardianQuery;
use AulaX\Students\Application\ListStudents\ListStudentsQuery;
use AulaX\Students\Application\ListStudents\StudentListItem;
use AulaX\Students\Application\Port\StudentPermissions;
use AulaX\Students\Application\ShowStudent\ShowStudentQuery;
use AulaX\Students\Application\StudentNotFound;
use AulaX\Students\Domain\Guardian\Guardian;
use AulaX\Students\Domain\Guardian\GuardianData;
use AulaX\Students\Domain\Guardian\GuardianRepository;
use AulaX\Students\Domain\Identity\GuardianDocument;
use AulaX\Students\Domain\Identity\StudentIdentification;
use AulaX\Students\Domain\Student\Guardianship;
use AulaX\Students\Domain\Student\HealthRecord;
use AulaX\Students\Domain\Student\PersonalData;
use AulaX\Students\Domain\Student\Relationship;
use AulaX\Students\Domain\Student\Student;
use AulaX\Students\Domain\Student\StudentId;
use AulaX\Students\Domain\Student\StudentRepository;
use AulaX\Students\Domain\Student\Withdrawal;
use Illuminate\Support\Facades\DB;
use Tests\Fixtures\Students\SwitchableStudentPermissions;

/**
 * SPEC-009 EST-R02.8, R03.3, R04.2, R04.3, R05: finding students and reading
 * their record, with or without their health data.
 */
beforeEach(function () {
    $this->permissions = new SwitchableStudentPermissions;
    app()->instance(StudentPermissions::class, $this->permissions);
});

/**
 * A student saved with its mother, identified by a cédula escolar formed
 * with her cédula.
 */
function queriedStudent(string $givenNames, string $familyNames, string $motherCedula, ?string $allergies = null): StudentId
{
    $guardians = app(GuardianRepository::class);
    $mother = $guardians->byDocument(GuardianDocument::fromInput('national_id', $motherCedula));

    if ($mother === null) {
        $mother = Guardian::register(GuardianData::fromInput('María', 'Mora', 'national_id', $motherCedula, '0414-5550000'));
        $mother->identify($guardians->save($mother));
    }

    $student = Student::register(
        PersonalData::fromInput($givenNames, $familyNames, 'female', '2019-03-04', 'V', null, null, null, null, null, new DateTimeImmutable('2026-10-21')),
        StudentIdentification::fromInput('school_id', 'V119'.str_pad(ltrim(substr($motherCedula, 1), '0'), 8, '0', STR_PAD_LEFT)),
        [new Guardianship($mother->id(), $mother->data()->document, Relationship::Mother, true, true)],
        HealthRecord::fromInput($allergies, null, null),
    );
    $id = app(StudentRepository::class)->save($student);

    return $id;
}

function listed(string $search = '', bool $withdrawn = false): array
{
    return array_map(fn (StudentListItem $item) => $item->fullName, app(ListStudentsQuery::class)($search, $withdrawn, 1)->items);
}

it('lists the active students of the institution by family name, with their identification', function () {
    tenant();
    queriedStudent('Ana', 'Pérez', 'V15432100');
    queriedStudent('Luis', 'Álvarez', 'V20111222');
    $page = app(ListStudentsQuery::class)('', false, 1);

    expect(listed())->toBe(['Álvarez, Luis', 'Pérez, Ana'])
        ->and($page->items[1]->identification)->toBe('V11915432100')
        ->and($page->items[1]->status)->toBe('active')
        ->and($page->total)->toBe(2);
});

it('shows the withdrawn students only when asked', function () {
    tenant();
    $id = queriedStudent('Ana', 'Pérez', 'V15432100');
    $student = app(StudentRepository::class)->byId($id);
    $student->withdraw(Withdrawal::fromInput('2026-10-01', 'moved', null, new DateTimeImmutable('2026-10-21')));
    app(StudentRepository::class)->save($student);

    expect(listed())->toBe([])
        ->and(listed(withdrawn: true))->toBe(['Pérez, Ana']);
});

it('finds students by name, family name or identification, including a former cédula escolar', function (string $search) {
    tenant();
    $id = queriedStudent('Ana Sofía', 'Pérez', 'V15432100');
    queriedStudent('Luis', 'Mora', 'V20111222');
    DB::table('student_previous_identifications')->insert(['student_id' => $id->value, 'identification' => 'V21915432199', 'replaced_on' => '2026-01-01']);

    expect(listed($search))->toBe(['Pérez, Ana Sofía']);
})->with([
    'given name' => ['sofía'],
    'family name' => ['PÉR'],
    'identification typed with dashes' => ['V-1-19-1543'],
    'former cédula escolar' => ['V21915432199'],
]);

it('escapes the wildcards of the search', function () {
    tenant();
    queriedStudent('Ana', 'Pérez', 'V15432100');

    expect(listed('%'))->toBe([])
        ->and(listed('_'))->toBe([]);
});

it('shows the record with its guardians and, to managers, its health data', function () {
    tenant();
    $id = queriedStudent('Ana', 'Pérez', 'V15432100', 'Penicilina');

    $view = app(ShowStudentQuery::class)($id->value, includeHealth: true);

    expect($view->givenNames)->toBe('Ana')
        ->and($view->guardians[0]->document)->toBe('V15432100')
        ->and($view->guardians[0]->isLegal)->toBeTrue()
        ->and($view->health->allergies)->toBe('Penicilina')
        ->and($view->hasHealthInfo)->toBeTrue();
});

it('only warns that there is medical information to whoever does not manage students', function () {
    tenant();
    $id = queriedStudent('Ana', 'Pérez', 'V15432100', 'Penicilina');
    $this->permissions->manage = false;

    $view = app(ShowStudentQuery::class)($id->value, includeHealth: true);

    expect($view->health)->toBeNull()
        ->and($view->hasHealthInfo)->toBeTrue();
});

it('answers not found for a student of another institution', function () {
    tenant();
    $id = queriedStudent('Ana', 'Pérez', 'V15432100');
    tenant();

    expect(fn () => app(ShowStudentQuery::class)($id->value, includeHealth: false))->toThrow(StudentNotFound::class);
});

it('finds a guardian of the institution by its document, to link it again', function () {
    tenant();
    queriedStudent('Ana', 'Pérez', 'V15432100');

    expect(app(FindGuardianQuery::class)('national_id', 'V-15.432.100')->givenNames)->toBe('María')
        ->and(app(FindGuardianQuery::class)('national_id', 'V1'))->toBeNull()
        ->and(app(FindGuardianQuery::class)('national_id', 'no es cédula'))->toBeNull();
});

test('the search answers within 300 ms (p95) among 5 000 students', function () {
    tenant();
    $rows = [];
    foreach (range(1, 5000) as $index) {
        $rows[] = [
            'given_names' => "Nombre {$index}", 'family_names' => "Apellido {$index}", 'sex' => 'male', 'birth_date' => '2015-01-01',
            'nationality' => 'V', 'identification_type' => 'national_id', 'identification' => 'V'.(30000000 + $index), 'status' => 'active',
        ];
    }
    foreach (array_chunk($rows, 1000) as $chunk) {
        DB::table('students')->insert($chunk);
    }
    $query = app(ListStudentsQuery::class);

    $timings = [];
    foreach (range(1, 40) as $call) {
        $began = hrtime(true);
        $query('apellido 49', false, 1);
        $timings[] = (hrtime(true) - $began) / 1e6;
    }
    sort($timings);

    expect($timings[(int) floor(count($timings) * 0.95)])->toBeLessThan(300.0);
})->group('perf');
