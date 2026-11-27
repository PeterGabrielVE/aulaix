<?php

use AulaX\Catalogs\Application\Subjects;
use AulaX\Evaluation\Application\ReportCard\StudentReportCardQuery;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\SubjectSeeder;
use Illuminate\Support\Facades\DB;

/**
 * SPEC-012 DOC-R01.1 – R01.3, R02.1 – R02.3: cross-module Evaluation query
 * builds term and final report card snapshots for Media General (including
 * revision), Primaria (literals) and Inicial (descriptive reports).
 */
test('builds term and final report card data for Inicial, Primaria and Media General including revisions and pendings', function () {
    $this->seed([CatalogSeeder::class, SubjectSeeder::class]);
    $institution = tenant(['subdomain' => 'doc-eval-query', 'name' => 'Colegio Eval']);
    $actor = userWithRole($institution, 'Administrador');

    $yearId = DB::table('school_years')->insertGetId([
        'starts_on' => '2026-09-16',
        'ends_on' => '2027-07-15',
        'status' => 'active',
    ]);
    foreach ([1 => ['2026-09-16', '2026-12-15'], 2 => ['2027-01-07', '2027-03-31'], 3 => ['2027-04-01', '2027-07-15']] as $num => [$start, $end]) {
        DB::table('terms')->insert([
            'school_year_id' => $yearId,
            'number' => $num,
            'starts_on' => $start,
            'ends_on' => $end,
            'status' => 'closed',
        ]);
    }

    // 1) Media General with revision
    $subjectId = app(Subjects::class)->ofLevel('media_general')[0]['id'];
    DB::table('curriculum_subjects')->insert(['school_year_id' => $yearId, 'grade' => 'media_1', 'subject_id' => $subjectId]);
    $mediaSectionId = DB::table('sections')->insertGetId(['school_year_id' => $yearId, 'grade' => 'media_1', 'letter' => 'A', 'capacity' => 30]);
    $mediaStudentId = DB::table('students')->insertGetId([
        'given_names' => 'Ana', 'family_names' => 'Pérez', 'sex' => 'female', 'birth_date' => '2013-02-01',
        'nationality' => 'V', 'identification_type' => 'national_id', 'identification' => 'V31000001', 'status' => 'active',
    ]);
    DB::table('enrollments')->insert([
        'student_id' => $mediaStudentId, 'school_year_id' => $yearId, 'section_id' => $mediaSectionId,
        'grade' => 'media_1', 'starts_on' => '2026-09-16', 'registered_by' => $actor->id, 'registered_at' => now(),
    ]);

    foreach ([1 => 800, 2 => 800, 3 => 800] as $term => $score) {
        $planId = DB::table('evaluation_plans')->insertGetId([
            'school_year_id' => $yearId, 'section_id' => $mediaSectionId, 'subject_id' => $subjectId, 'term' => $term,
        ]);
        $activityId = DB::table('evaluation_activities')->insertGetId([
            'plan_id' => $planId, 'name' => "Prueba {$term}", 'date' => $term === 1 ? '2026-10-15' : ($term === 2 ? '2027-02-15' : '2027-05-15'),
            'weight' => 10000, 'position' => 1,
        ]);
        DB::table('activity_grades')->insert([
            'activity_id' => $activityId, 'student_id' => $mediaStudentId, 'score' => $score, 'not_presented' => false,
        ]);
    }
    DB::table('grade_revisions')->insert([
        'school_year_id' => $yearId, 'subject_id' => $subjectId, 'section_id' => $mediaSectionId,
        'student_id' => $mediaStudentId, 'score' => 1200, 'not_presented' => false,
    ]);

    // 2) Primaria with literals
    $primSectionId = DB::table('sections')->insertGetId(['school_year_id' => $yearId, 'grade' => 'primaria_3', 'letter' => 'A', 'capacity' => 30]);
    $primStudentId = DB::table('students')->insertGetId([
        'given_names' => 'Luis', 'family_names' => 'Gómez', 'sex' => 'male', 'birth_date' => '2018-02-01',
        'nationality' => 'V', 'identification_type' => 'school_id', 'identification' => '11820000002', 'status' => 'active',
    ]);
    DB::table('student_marks')->insert([
        ['student_id' => $primStudentId, 'school_year_id' => $yearId, 'period' => '1', 'kind' => 'literal', 'literal' => 'A', 'description' => 'Excelente desempeño', 'incomplete' => false],
        ['student_id' => $primStudentId, 'school_year_id' => $yearId, 'period' => 'final', 'kind' => 'literal', 'literal' => 'A', 'description' => 'Aprobado con distinción', 'incomplete' => false],
    ]);

    // 3) Inicial with descriptive report
    $initSectionId = DB::table('sections')->insertGetId(['school_year_id' => $yearId, 'grade' => 'preescolar_3', 'letter' => 'A', 'capacity' => 25]);
    $initStudentId = DB::table('students')->insertGetId([
        'given_names' => 'Sofía', 'family_names' => 'Díaz', 'sex' => 'female', 'birth_date' => '2021-05-01',
        'nationality' => 'V', 'identification_type' => 'school_id', 'identification' => '12120000003', 'status' => 'active',
    ]);
    DB::table('student_marks')->insert([
        'student_id' => $initStudentId, 'school_year_id' => $yearId, 'period' => '1', 'kind' => 'report', 'literal' => null, 'description' => 'Participa con entusiasmo.', 'incomplete' => false,
    ]);

    $query = app(StudentReportCardQuery::class);

    $mediaFinal = $query->forStudents($yearId, $mediaSectionId, [$mediaStudentId], null)[$mediaStudentId];
    expect($mediaFinal->level)->toBe('media_general')
        ->and($mediaFinal->subjects)->toHaveCount(1)
        ->and($mediaFinal->subjects[0]->term1)->toBe('8')
        ->and($mediaFinal->subjects[0]->finalScore)->toBe('8')
        ->and($mediaFinal->subjects[0]->revision)->toBe('12')
        ->and($mediaFinal->subjects[0]->finalAfterRevision)->toBe('12')
        ->and($mediaFinal->subjects[0]->failed)->toBeFalse();

    $primaria = $query->forStudents($yearId, $primSectionId, [$primStudentId], null)[$primStudentId];
    expect($primaria->level)->toBe('primaria')
        ->and($primaria->primaryMarks['1']['literal'])->toBe('A')
        ->and($primaria->primaryMarks['final']['literal'])->toBe('A');

    $inicial = $query->forStudents($yearId, $initSectionId, [$initStudentId], 1)[$initStudentId];
    expect($inicial->level)->toBe('inicial')
        ->and($inicial->initialReports['1']['description'])->toBe('Participa con entusiasmo.');
});
