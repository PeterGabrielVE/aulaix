<?php

use AulaX\Tenancy\Infrastructure\Persistence\RowLevelSecurity;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * SPEC-009 EST-R11.1 (S-01): students, guardians and their links belong to
 * their institution, isolated by the database. Raw SQL on purpose.
 */
pest()->group('rls');

/**
 * A student with its guardian, linked, and a withdrawal and a former
 * cédula escolar: one row in each table of the module.
 *
 * @return array{student: int, guardian: int}
 */
function rawStudent(string $identification = 'V11915432100', string $document = 'V15432100'): array
{
    $student = DB::table('students')->insertGetId([
        'given_names' => 'Ana', 'family_names' => 'Pérez', 'sex' => 'female', 'birth_date' => '2019-03-04', 'nationality' => 'V',
        'identification_type' => 'school_id', 'identification' => $identification, 'status' => 'active',
    ]);
    $guardian = DB::table('guardians')->insertGetId([
        'given_names' => 'María', 'family_names' => 'Mora', 'document_type' => 'national_id', 'document' => $document, 'phone' => '0414',
    ]);
    DB::table('guardianships')->insert(['student_id' => $student, 'guardian_id' => $guardian, 'relationship' => 'mother', 'is_legal' => true, 'is_primary' => true]);
    DB::table('student_withdrawals')->insert(['student_id' => $student, 'withdrawn_on' => '2026-01-10', 'reason' => 'moved']);
    DB::table('student_previous_identifications')->insert(['student_id' => $student, 'identification' => 'V11915432199', 'replaced_on' => '2026-01-10']);

    return ['student' => $student, 'guardian' => $guardian];
}

test('an institution only sees its own students, guardians and their records', function (string $table) {
    tenant();
    rawStudent();
    $own = tenant();
    rawStudent();

    expect(DB::table($table)->pluck('institution_id')->unique()->values()->all())->toBe([$own->id]);
})->with(['students', 'guardians', 'guardianships', 'student_withdrawals', 'student_previous_identifications']);

test('with no institution active no student is visible', function () {
    tenant();
    rawStudent();
    RowLevelSecurity::clearInstitution();

    expect(DB::table('students')->count())->toBe(0)
        ->and(DB::table('guardians')->count())->toBe(0);
});

test('an identification and a guardian document are unique in the institution, not across institutions', function () {
    tenant();
    rawStudent();

    expect(fn () => DB::transaction(fn () => rawStudent('V11915432100', 'V20111222')))->toThrow(QueryException::class)
        ->and(fn () => DB::transaction(fn () => rawStudent('V11920111222', 'V15432100')))->toThrow(QueryException::class);

    tenant();
    rawStudent();

    expect(DB::table('students')->count())->toBe(1);
});

test('a student has one primary contact at most', function () {
    tenant();
    ['student' => $student] = rawStudent();
    $father = DB::table('guardians')->insertGetId(['given_names' => 'Luis', 'family_names' => 'Pérez', 'document_type' => 'national_id', 'document' => 'V20111222', 'phone' => '0414']);

    expect(fn () => DB::transaction(fn () => DB::table('guardianships')->insert([
        'student_id' => $student, 'guardian_id' => $father, 'relationship' => 'father', 'is_legal' => false, 'is_primary' => true,
    ])))->toThrow(QueryException::class);
});

test('the known values are enforced by the database', function (string $table, array $change) {
    tenant();
    $ids = rawStudent();
    $id = $table === 'guardians' ? $ids['guardian'] : $ids['student'];
    $query = $table === 'guardianships' ? DB::table($table)->where('student_id', $ids['student']) : ($table === 'student_withdrawals' ? DB::table($table)->where('student_id', $ids['student']) : DB::table($table)->where('id', $id));

    expect(fn () => DB::transaction(fn () => $query->update($change)))->toThrow(QueryException::class);
})->with([
    'sex' => ['students', ['sex' => 'other']],
    'nationality' => ['students', ['nationality' => 'X']],
    'identification type' => ['students', ['identification_type' => 'passport']],
    'status' => ['students', ['status' => 'expelled']],
    'document type' => ['guardians', ['document_type' => 'licence']],
    'relationship' => ['guardianships', ['relationship' => 'uncle']],
    'withdrawal reason' => ['student_withdrawals', ['reason' => 'expulsion']],
]);
