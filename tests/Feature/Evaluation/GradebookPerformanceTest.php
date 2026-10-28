<?php

use AulaX\Evaluation\Application\Gradebook\GradebookQuery;
use AulaX\Evaluation\Application\SaveActivityGrades\SaveActivityGradesHandler;
use Illuminate\Support\Facades\DB;

require_once dirname(__DIR__, 2).'/Fixtures/Evaluation/evaluation_helpers.php';

/**
 * SPEC-011 EVA-NF04: saving the 40 grades of an activity takes under 500 ms
 * and the sheet of 40 students and 10 activities under 300 ms (p95), with
 * the plans of two sections of the subject.
 */
beforeEach(function () {
    foreach (evaluationSetting() as $name => $value) {
        $this->{$name} = $value;
    }

    $this->actingAs($this->teacher);
    $this->students = [$this->ana, $this->luis];

    foreach (range(1, 38) as $number) {
        $student = enrollmentStudent('Estudiante', "N{$number}", '2011-01-01');
        enrollmentRow($student, $this->year, $this->section, ['grade' => 'media_3']);
        $this->students[] = $student;
    }

    $other = enrollmentSection($this->year, 'media_3', 'B');

    foreach ([$this->section, $other] as $section) {
        $plan = evaluationPlanRow($this->year, $section, $this->math);

        foreach (range(1, 10) as $position) {
            $activity = evaluationActivityRow($plan, ['name' => "Actividad {$position}", 'date' => '2026-10-'.str_pad((string) $position, 2, '0', STR_PAD_LEFT), 'weight' => 1000, 'position' => $position]);

            if ($section === $this->section) {
                $this->activities[] = $activity;
                DB::table('activity_grades')->insert(array_map(fn (int $student) => [
                    'activity_id' => $activity, 'student_id' => $student, 'score' => 1500, 'not_presented' => false,
                ], $this->students));
            }
        }
    }
});

function p95(callable $run, int $times = 20): float
{
    $run();
    $durations = [];

    foreach (range(1, $times) as $time) {
        $started = hrtime(true);
        $run($time);
        $durations[] = (hrtime(true) - $started) / 1e6;
    }

    sort($durations);

    return $durations[(int) ceil(0.95 * $times) - 1];
}

it('saves the 40 grades of an activity in under 500 ms', function () {
    $save = app(SaveActivityGradesHandler::class);

    $milliseconds = p95(fn (int $time = 0) => $save($this->activities[0], array_fill_keys($this->students, (string) (10 + $time % 10))));

    expect($milliseconds)->toBeLessThan(500.0);
})->group('perf');

it('shows the sheet of 40 students and 10 activities in under 300 ms', function () {
    $gradebook = app(GradebookQuery::class);

    $milliseconds = p95(function () use ($gradebook) {
        expect($gradebook($this->year, $this->section, $this->math, 1)->rows)->toHaveCount(40);
    });

    expect($milliseconds)->toBeLessThan(300.0);
})->group('perf');
