<?php

use AulaX\Enrollment\Application\Roster\PromotionSheetQuery;
use AulaX\Evaluation\Application\PendingGroups\PendingGroupSheetQuery;
use Illuminate\Support\Facades\DB;

require_once dirname(__DIR__, 2).'/Fixtures/Evaluation/evaluation_helpers.php';

/**
 * SPEC-016 RVP-NF04: the sheet of a group of 40 students and 10 activities
 * under 300 ms, and the proposal of a section of 40, with revisions and
 * pending subjects, under 500 ms (p95).
 */
beforeEach(function () {
    foreach (evaluationSetting() as $name => $value) {
        $this->{$name} = $value;
    }

    $this->students = [$this->ana, $this->luis];

    foreach (range(1, 38) as $number) {
        $student = enrollmentStudent('Estudiante', "N{$number}", '2011-01-01');
        enrollmentRow($student, $this->year, $this->section, ['grade' => 'media_3']);
        $this->students[] = $student;
    }

    // The 40 owe Matemática of 2.º año, from the year before.
    $before = enrollmentYear(2025, 'closed');
    $previousSection = enrollmentSection($before, 'media_2');

    foreach ($this->students as $student) {
        $previous = enrollmentRow($student, $before, $previousSection, [
            'grade' => 'media_2', 'starts_on' => '2025-09-16', 'ends_on' => '2026-07-15', 'end_reason' => 'section_change',
            'outcome' => 'promoted_with_pending', 'outcome_recorded_at' => now(), 'outcome_recorded_by' => 1,
        ]);
        DB::table('enrollment_pending_subjects')->insert(['enrollment_id' => $previous, 'subject_id' => $this->math]);
    }

    $this->group = DB::table('pending_groups')->insertGetId(['school_year_id' => $this->year, 'subject_id' => $this->math, 'origin_grade' => 'media_2', 'teacher_account_id' => $this->teacher->id]);
    $dates = [1 => '2026-10-', 2 => '2027-02-', 3 => '2027-05-'];

    // Ten activities a term in the group; three a term in each subject of the section.
    foreach ($dates as $term => $month) {
        $groupPlan = DB::table('evaluation_plans')->insertGetId(['school_year_id' => $this->year, 'subject_id' => $this->math, 'term' => $term, 'pending_group_id' => $this->group]);
        perfActivities($groupPlan, $month, 10, 1500);

        foreach ([$this->math, $this->physics] as $subject) {
            perfActivities(evaluationPlanRow($this->year, $this->section, $subject, $term), $month, 3, $subject === $this->physics ? 800 : 1500);
        }
    }

    // Everyone fails Física: half pass its revision, half do not present it.
    foreach ($this->students as $index => $student) {
        revisionRow($this->year, $this->section, $this->physics, $student, $index % 2 === 0 ? ['score' => 1200, 'not_presented' => false] : []);
    }

    DB::table('terms')->update(['status' => 'closed']);
});

function perfActivities(int $planId, string $month, int $count, int $score): void
{
    foreach (range(1, $count) as $position) {
        $activity = evaluationActivityRow($planId, ['name' => "Actividad {$position}", 'date' => $month.str_pad((string) ($position + 1), 2, '0', STR_PAD_LEFT), 'weight' => intdiv(10000, $count) + ($position === $count ? 10000 % $count : 0), 'position' => $position]);
        DB::table('activity_grades')->insert(array_map(fn (int $student) => [
            'activity_id' => $activity, 'student_id' => $student, 'score' => $score, 'not_presented' => false,
        ], test()->students));
    }
}

function pendingP95(callable $run, int $times = 20): float
{
    $run();
    $durations = [];

    foreach (range(1, $times) as $time) {
        $started = hrtime(true);
        $run();
        $durations[] = (hrtime(true) - $started) / 1e6;
    }

    sort($durations);

    return $durations[(int) ceil(0.95 * $times) - 1];
}

it('shows the sheet of a group of 40 students and 10 activities in under 300 ms', function () {
    $this->actingAs($this->teacher);
    $sheet = app(PendingGroupSheetQuery::class);

    $milliseconds = pendingP95(function () use ($sheet) {
        expect($sheet($this->group, 1)->rows)->toHaveCount(40);
    });

    expect($milliseconds)->toBeLessThan(300.0);
})->group('perf');

it('proposes the result of a section of 40, with revisions and pending subjects, in under 500 ms', function () {
    $this->actingAs(userWithRole($this->institution, 'Director'));
    $promotion = app(PromotionSheetQuery::class);

    $milliseconds = pendingP95(function () use ($promotion) {
        $outcomes = array_count_values(array_map(fn ($row) => (string) $row->proposal?->outcome, $promotion($this->year, $this->section)->students));

        expect($outcomes)->toEqual(['promoted' => 20, 'promoted_with_pending' => 20]);
    });

    expect($milliseconds)->toBeLessThan(500.0);
})->group('perf');
