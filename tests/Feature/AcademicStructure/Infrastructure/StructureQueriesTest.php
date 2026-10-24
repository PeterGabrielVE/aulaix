<?php

use AulaX\AcademicStructure\Application\Port\StructurePermissions;
use AulaX\AcademicStructure\Application\SectionsOfYear\SectionsOfYearQuery;
use AulaX\AcademicStructure\Application\ShowStructure\ShowStructureQuery;
use AulaX\Shared\Domain\AuthorizationDenied;
use Illuminate\Support\Facades\DB;

/**
 * SPEC-008 EAC-R08.1, R08.3, NF03: the structure of a school year as its
 * screen shows it, and the sections other modules read.
 */
beforeEach(function () {
    $this->canView = true;
    $test = $this;
    app()->instance(StructurePermissions::class, new class($test) implements StructurePermissions
    {
        public function __construct(private readonly object $test) {}

        public function canManage(): bool
        {
            return true;
        }

        public function canView(): bool
        {
            return $this->test->canView;
        }
    });
});

/**
 * An institution offering Primaria, with a closed and an active year.
 *
 * @return array{closed: int, active: int, math: int}
 */
function structuredInstitution(): array
{
    tenant(['education_levels' => ['primaria']]);
    $closed = DB::table('school_years')->insertGetId(['starts_on' => '2025-09-16', 'ends_on' => '2026-07-15', 'status' => 'closed']);
    $active = DB::table('school_years')->insertGetId(['starts_on' => '2026-09-16', 'ends_on' => '2027-07-15', 'status' => 'active']);
    $math = DB::table('subjects')->insertGetId(['code' => 'T-'.uniqid(), 'name' => 'Matemática', 'education_level' => 'primaria']);
    DB::table('sections')->insert([
        ['school_year_id' => $active, 'grade' => 'primaria_2', 'letter' => 'A', 'capacity' => 30, 'capacity_reason' => null],
        ['school_year_id' => $active, 'grade' => 'primaria_1', 'letter' => 'B', 'capacity' => 38, 'capacity_reason' => 'Alta demanda'],
        ['school_year_id' => $active, 'grade' => 'primaria_1', 'letter' => 'A', 'capacity' => 30, 'capacity_reason' => null],
    ]);
    DB::table('curriculum_subjects')->insert(['school_year_id' => $active, 'grade' => 'primaria_1', 'subject_id' => $math]);

    return ['closed' => $closed, 'active' => $active, 'math' => $math];
}

it('shows the active year by default: its grades, sections in order and curriculum', function () {
    ['active' => $active, 'math' => $math] = structuredInstitution();

    $view = app(ShowStructureQuery::class)(null);

    expect($view->schoolYearId)->toBe($active)
        ->and($view->status)->toBe('active')
        ->and(array_column($view->years, 'name'))->toBe(['2026-2027', '2025-2026'])
        ->and(array_column($view->grades, 'code'))->toBe(['primaria_1', 'primaria_2', 'primaria_3', 'primaria_4', 'primaria_5', 'primaria_6'])
        ->and(array_map(fn ($section) => $section->grade.$section->letter, $view->sections))->toBe(['primaria_1A', 'primaria_1B', 'primaria_2A'])
        ->and($view->sections[1]->capacityReason)->toBe('Alta demanda')
        ->and($view->curriculum['primaria_1'])->toBe([['id' => $math, 'name' => 'Matemática']])
        ->and(array_column($view->subjectOptions['primaria'], 'id'))->toContain($math);
});

it('shows a chosen year', function () {
    ['closed' => $closed] = structuredInstitution();

    $view = app(ShowStructureQuery::class)($closed);

    expect($view->status)->toBe('closed')
        ->and($view->sections)->toBe([]);
});

it('is seen only with ver-estructura', function () {
    structuredInstitution();
    $this->canView = false;

    expect(fn () => app(ShowStructureQuery::class)(null))->toThrow(AuthorizationDenied::class);
});

it('tells other modules the sections of a year with their capacity', function () {
    ['active' => $active] = structuredInstitution();

    $sections = app(SectionsOfYearQuery::class)($active);

    expect(array_map(fn ($section) => [$section->grade, $section->letter, $section->capacity], $sections))
        ->toBe([['primaria_1', 'A', 30], ['primaria_1', 'B', 38], ['primaria_2', 'A', 30]]);
});

test('the structure of a year with 60 sections loads within 300 ms (p95)', function () {
    ['active' => $active] = structuredInstitution();
    DB::table('sections')->delete();
    $rows = [];
    foreach (['primaria_1', 'primaria_2', 'primaria_3', 'primaria_4', 'primaria_5', 'primaria_6'] as $grade) {
        foreach (range('A', 'J') as $letter) {
            $rows[] = ['school_year_id' => $active, 'grade' => $grade, 'letter' => $letter, 'capacity' => 30];
        }
    }
    DB::table('sections')->insert($rows);
    $query = app(ShowStructureQuery::class);

    $timings = [];
    foreach (range(1, 20) as $call) {
        $began = hrtime(true);
        $query($active);
        $timings[] = (hrtime(true) - $began) / 1e6;
    }
    sort($timings);

    expect($timings[(int) floor(count($timings) * 0.95)])->toBeLessThan(300.0);
})->group('perf');
