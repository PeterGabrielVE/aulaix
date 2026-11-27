<?php

use AulaX\AcademicStructure\Application\Overview\CoordinatedGradesQuery;
use AulaX\AcademicStructure\Application\Overview\StructureOverviewQuery;
use AulaX\AcademicStructure\Application\Port\StructurePermissions;
use AulaX\Shared\Domain\AuthorizationDenied;
use Illuminate\Support\Facades\DB;

/**
 * SPEC-013 NAV-R05.1, R05.3, R06.1 – R06.3: the figures and pending tasks of
 * the active year's structure, for the whole institution or some grades.
 */
beforeEach(function () {
    $this->canView = true;
    $test = $this;
    app()->instance(StructurePermissions::class, new class($test) implements StructurePermissions
    {
        public function __construct(private readonly object $test) {}

        public function canManage(): bool
        {
            return false;
        }

        public function canView(): bool
        {
            return $this->test->canView;
        }
    });

    $this->institution = tenant(['education_levels' => ['inicial', 'primaria']]);
    $closed = DB::table('school_years')->insertGetId(['starts_on' => '2025-09-16', 'ends_on' => '2026-07-15', 'status' => 'closed']);
    $this->active = DB::table('school_years')->insertGetId(['starts_on' => '2026-09-16', 'ends_on' => '2027-07-15', 'status' => 'active']);
    $math = DB::table('subjects')->insertGetId(['code' => 'T-'.uniqid(), 'name' => 'Matemática', 'education_level' => 'primaria']);
    $arts = DB::table('subjects')->insertGetId(['code' => 'T-'.uniqid(), 'name' => 'Artes', 'education_level' => 'primaria']);
    $this->teacher = userWithRole($this->institution, 'Docente');
    $this->gone = userWithRole($this->institution, 'Docente');
    $this->coordinator = userWithRole($this->institution, 'Coordinador');
    DB::table('institution_memberships')->where('user_id', $this->gone->id)->update(['status' => 'ended']);

    // 1.er grado: A guided and fully taught; B without guide, Artes without teacher.
    $a = DB::table('sections')->insertGetId(['school_year_id' => $this->active, 'grade' => 'primaria_1', 'letter' => 'A', 'capacity' => 30, 'guide_account_id' => $this->teacher->id]);
    $b = DB::table('sections')->insertGetId(['school_year_id' => $this->active, 'grade' => 'primaria_1', 'letter' => 'B', 'capacity' => 25]);
    // 2.º grado: guided by someone who left.
    DB::table('sections')->insert(['school_year_id' => $this->active, 'grade' => 'primaria_2', 'letter' => 'A', 'capacity' => 35, 'guide_account_id' => $this->gone->id]);
    // A closed year counts for nothing.
    DB::table('sections')->insert(['school_year_id' => $closed, 'grade' => 'primaria_3', 'letter' => 'A', 'capacity' => 30]);
    DB::table('curriculum_subjects')->insert([
        ['school_year_id' => $this->active, 'grade' => 'primaria_1', 'subject_id' => $math],
        ['school_year_id' => $this->active, 'grade' => 'primaria_1', 'subject_id' => $arts],
    ]);
    DB::table('teaching_assignments')->insert([
        ['section_id' => $a, 'subject_id' => $math, 'account_id' => $this->teacher->id],
        ['section_id' => $a, 'subject_id' => $arts, 'account_id' => $this->gone->id],
        ['section_id' => $b, 'subject_id' => $math, 'account_id' => $this->teacher->id],
    ]);
    DB::table('coordinator_assignments')->insert([
        ['school_year_id' => $this->active, 'account_id' => $this->coordinator->id, 'level' => 'primaria', 'grade' => 'primaria_2'],
        ['school_year_id' => $this->active, 'account_id' => $this->coordinator->id, 'level' => 'inicial', 'grade' => null],
        ['school_year_id' => $closed, 'account_id' => $this->coordinator->id, 'level' => 'primaria', 'grade' => 'primaria_3'],
    ]);
});

test('counts the sections, capacity and pending tasks of the active year', function () {
    $overview = app(StructureOverviewQuery::class)();

    expect($overview->schoolYearId)->toBe($this->active)
        ->and($overview->sections)->toBe(3)
        ->and($overview->capacity)->toBe(90)
        ->and($overview->gradesWithoutSections)->toBe([
            'maternal_1', 'maternal_2', 'maternal_3', 'preescolar_1', 'preescolar_2', 'preescolar_3',
            'primaria_3', 'primaria_4', 'primaria_5', 'primaria_6',
        ])
        ->and($overview->sectionsWithoutGuide)->toBe(1)
        ->and($overview->subjectsWithoutTeacher)->toBe(1)
        ->and($overview->assignmentsOfInactivePeople)->toBe(2);
});

test('limits everything to the given grades', function () {
    $overview = app(StructureOverviewQuery::class)(['primaria_2']);

    expect($overview->sections)->toBe(1)
        ->and($overview->capacity)->toBe(35)
        ->and($overview->gradesWithoutSections)->toBe([])
        ->and($overview->sectionsWithoutGuide)->toBe(0)
        ->and($overview->subjectsWithoutTeacher)->toBe(0)
        ->and($overview->assignmentsOfInactivePeople)->toBe(1);
});

test('has nothing to count without an active year', function () {
    DB::table('school_years')->where('id', $this->active)->update(['status' => 'closed']);

    expect(app(StructureOverviewQuery::class)())->toBeNull();
});

test('tells the grades a coordinator has in the active year, whole levels included', function () {
    expect(app(CoordinatedGradesQuery::class)($this->coordinator->id))->toBe([
        'maternal_1', 'maternal_2', 'maternal_3', 'preescolar_1', 'preescolar_2', 'preescolar_3', 'primaria_2',
    ])
        ->and(app(CoordinatedGradesQuery::class)($this->teacher->id))->toBe([]);
});

test('needs ver-estructura', function () {
    $this->canView = false;

    expect(fn () => app(StructureOverviewQuery::class)())->toThrow(AuthorizationDenied::class)
        ->and(fn () => app(CoordinatedGradesQuery::class)($this->coordinator->id))->toThrow(AuthorizationDenied::class);
});
