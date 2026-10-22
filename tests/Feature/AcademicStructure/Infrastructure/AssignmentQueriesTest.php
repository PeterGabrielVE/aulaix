<?php

use AulaX\AcademicStructure\Application\MyAssignments\MyAssignmentsQuery;
use AulaX\AcademicStructure\Application\Port\StructurePermissions;
use AulaX\AcademicStructure\Application\ShowStructure\ShowStructureQuery;
use AulaX\AcademicStructure\Application\TeachingQuery;
use Illuminate\Support\Facades\DB;

/**
 * SPEC-008 EAC-R05.6, R08.2, R08.4, R08.5, NF02: who is assigned, what each
 * teacher has, and the question SPEC-011 will ask on every grade.
 */
beforeEach(function () {
    app()->instance(StructurePermissions::class, new class implements StructurePermissions
    {
        public function canManage(): bool
        {
            return true;
        }

        public function canView(): bool
        {
            return true;
        }
    });

    $this->institution = tenant(['education_levels' => ['primaria']]);
    $this->closed = DB::table('school_years')->insertGetId(['starts_on' => '2025-09-16', 'ends_on' => '2026-07-15', 'status' => 'closed']);
    $this->active = DB::table('school_years')->insertGetId(['starts_on' => '2026-09-16', 'ends_on' => '2027-07-15', 'status' => 'active']);
    $this->math = DB::table('subjects')->insertGetId(['code' => 'T-'.uniqid(), 'name' => 'Matemática', 'education_level' => 'primaria']);
    $this->arts = DB::table('subjects')->insertGetId(['code' => 'T-'.uniqid(), 'name' => 'Artes', 'education_level' => 'primaria']);
    $this->teacher = userWithRole($this->institution, 'Docente', ['name' => 'Ana Pérez']);
    $this->gone = userWithRole($this->institution, 'Docente', ['name' => 'Luis Mora']);
    $this->section = DB::table('sections')->insertGetId(['school_year_id' => $this->active, 'grade' => 'primaria_1', 'letter' => 'A', 'capacity' => 30, 'guide_account_id' => $this->teacher->id]);
    $this->oldSection = DB::table('sections')->insertGetId(['school_year_id' => $this->closed, 'grade' => 'primaria_1', 'letter' => 'A', 'capacity' => 30]);
    DB::table('curriculum_subjects')->insert([
        ['school_year_id' => $this->active, 'grade' => 'primaria_1', 'subject_id' => $this->math],
        ['school_year_id' => $this->active, 'grade' => 'primaria_1', 'subject_id' => $this->arts],
    ]);
    DB::table('teaching_assignments')->insert([
        ['section_id' => $this->section, 'subject_id' => $this->math, 'account_id' => $this->teacher->id],
        ['section_id' => $this->section, 'subject_id' => $this->arts, 'account_id' => $this->gone->id],
        ['section_id' => $this->oldSection, 'subject_id' => $this->math, 'account_id' => $this->teacher->id],
    ]);
});

it('names the people assigned and flags, without removing, those who are no longer active members', function () {
    DB::table('institution_memberships')->where('user_id', $this->gone->id)->update(['status' => 'ended']);

    $view = app(ShowStructureQuery::class)($this->active);

    expect($view->people[$this->teacher->id])->toBe(['name' => 'Ana Pérez', 'active' => true])
        ->and($view->people[$this->gone->id])->toBe(['name' => 'Luis Mora', 'active' => false])
        ->and($view->sections[0]->teachers)->toBe([$this->math => $this->teacher->id, $this->arts => $this->gone->id])
        ->and($view->orphans)->toBe([['sectionId' => $this->section, 'subjectId' => $this->arts, 'accountId' => $this->gone->id]])
        ->and(array_column($view->teacherCandidates, 'name'))->toBe(['Ana Pérez']);
});

it('lists a teacher assignments of the active year', function () {
    $mine = app(MyAssignmentsQuery::class)($this->teacher->id);

    expect($mine->schoolYearId)->toBe($this->active)
        ->and($mine->teaching)->toBe([['sectionId' => $this->section, 'section' => '1.er grado A', 'subjectId' => $this->math, 'subject' => 'Matemática']])
        ->and($mine->guiding)->toBe([['sectionId' => $this->section, 'section' => '1.er grado A']]);
});

it('tells other modules whether a person teaches a subject in a section of the active year, and their sections', function () {
    $teaching = app(TeachingQuery::class);

    expect($teaching->teaches($this->teacher->id, $this->section, $this->math))->toBeTrue()
        ->and($teaching->teaches($this->teacher->id, $this->section, $this->arts))->toBeFalse()
        ->and($teaching->teaches($this->teacher->id, $this->oldSection, $this->math))->toBeFalse()
        ->and($teaching->sectionsOf($this->teacher->id))->toBe([$this->section]);
});

test('asking whether a person teaches answers within 10 ms (p95)', function () {
    $teaching = app(TeachingQuery::class);

    $timings = [];
    foreach (range(1, 200) as $call) {
        $began = hrtime(true);
        $teaching->teaches($this->teacher->id, $this->section, $this->math);
        $timings[] = (hrtime(true) - $began) / 1e6;
    }
    sort($timings);

    expect($timings[(int) floor(count($timings) * 0.95)])->toBeLessThan(10.0);
})->group('perf');
