<?php

use AulaX\Tenancy\Application\CurrentTenant;
use AulaX\Tenancy\Infrastructure\Persistence\InstitutionModel;
use AulaX\Tenancy\Infrastructure\Persistence\RowLevelSecurity;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * SPEC-008 EAC-R12.1 (S-01): the structure of a school year belongs to its
 * institution, isolated by the database. Raw SQL on purpose.
 */
pest()->group('rls');

/**
 * A school year with one row in each structure table.
 *
 * @return array{year: int, section: int, subject: int}
 */
function rawStructure(string $letter = 'A'): array
{
    $year = DB::table('school_years')->insertGetId(['starts_on' => '2026-09-16', 'ends_on' => '2027-07-15', 'status' => 'planned']);
    $subject = DB::table('subjects')->insertGetId(['code' => 'T-'.uniqid(), 'name' => 'Matemática', 'education_level' => 'primaria']);
    $section = DB::table('sections')->insertGetId(['school_year_id' => $year, 'grade' => 'primaria_1', 'letter' => $letter, 'capacity' => 30]);
    DB::table('curriculum_subjects')->insert(['school_year_id' => $year, 'grade' => 'primaria_1', 'subject_id' => $subject]);
    DB::table('teaching_assignments')->insert(['section_id' => $section, 'subject_id' => $subject, 'account_id' => null]);
    DB::table('coordinator_assignments')->insert(['school_year_id' => $year, 'account_id' => userWithRole(currentInstitution(), 'Coordinador')->id, 'level' => 'primaria']);

    return ['year' => $year, 'section' => $section, 'subject' => $subject];
}

function currentInstitution(): InstitutionModel
{
    return InstitutionModel::query()
        ->findOrFail(app(CurrentTenant::class)->institutionId()->value);
}

test('an institution only sees its own structure', function (string $table) {
    tenant();
    rawStructure();
    $own = tenant();
    rawStructure();

    expect(DB::table($table)->pluck('institution_id')->unique()->values()->all())->toBe([$own->id]);
})->with(['sections', 'curriculum_subjects', 'teaching_assignments', 'coordinator_assignments']);

test('with no institution active no structure is visible', function () {
    tenant();
    rawStructure();
    RowLevelSecurity::clearInstitution();

    expect(DB::table('sections')->count())->toBe(0)
        ->and(DB::table('teaching_assignments')->count())->toBe(0);
});

test('the unique rules of the structure are enforced by the database', function () {
    tenant();
    ['year' => $year, 'section' => $section, 'subject' => $subject] = rawStructure();

    expect(fn () => DB::transaction(fn () => DB::table('sections')->insert(['school_year_id' => $year, 'grade' => 'primaria_1', 'letter' => 'A', 'capacity' => 30])))->toThrow(QueryException::class)
        ->and(fn () => DB::transaction(fn () => DB::table('curriculum_subjects')->insert(['school_year_id' => $year, 'grade' => 'primaria_1', 'subject_id' => $subject])))->toThrow(QueryException::class)
        ->and(fn () => DB::transaction(fn () => DB::table('teaching_assignments')->insert(['section_id' => $section, 'subject_id' => $subject])))->toThrow(QueryException::class);
});

test('the known values are enforced by the database', function (string $table, array $change) {
    tenant();
    rawStructure();

    expect(fn () => DB::transaction(fn () => DB::table($table)->update($change)))->toThrow(QueryException::class);
})->with([
    'grade' => ['sections', ['grade' => 'primaria_7']],
    'letter' => ['sections', ['letter' => 'a']],
    'capacity' => ['sections', ['capacity' => 0]],
    'curriculum grade' => ['curriculum_subjects', ['grade' => 'universidad']],
    'coordinator level' => ['coordinator_assignments', ['level' => 'universitario']],
    'coordinator grade' => ['coordinator_assignments', ['grade' => 'primaria_9']],
]);
