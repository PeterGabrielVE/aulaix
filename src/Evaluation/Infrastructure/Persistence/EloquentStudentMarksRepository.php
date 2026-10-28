<?php

declare(strict_types=1);

namespace AulaX\Evaluation\Infrastructure\Persistence;

use AulaX\Evaluation\Domain\Literal;
use AulaX\Evaluation\Domain\Mark;
use AulaX\Evaluation\Domain\MarkKind;
use AulaX\Evaluation\Domain\StudentMarks;
use AulaX\Evaluation\Domain\StudentMarksRepository;
use Illuminate\Support\Facades\DB;

/**
 * The literals and reports of the open institution, through the query
 * builder (Row Level Security filters every query). A mark is never
 * removed, only replaced: one row per student, year, period and kind.
 */
final class EloquentStudentMarksRepository implements StudentMarksRepository
{
    public function forStudents(int $schoolYearId, array $studentIds): array
    {
        $rows = DB::table('student_marks')
            ->where('school_year_id', $schoolYearId)
            ->whereIn('student_id', $studentIds)
            ->get()
            ->groupBy('student_id');
        $marks = [];

        foreach ($studentIds as $studentId) {
            $marks[$studentId] = StudentMarks::of($studentId, $schoolYearId, ($rows[$studentId] ?? collect())->map(fn (object $row) => new Mark(
                $row->period,
                MarkKind::from($row->kind),
                $row->literal === null ? null : Literal::from($row->literal),
                $row->description,
                (bool) $row->incomplete,
            ))->values()->all());
        }

        return $marks;
    }

    public function saveMarks(StudentMarks $marks): void
    {
        if ($marks->marks() === []) {
            return;
        }

        DB::table('student_marks')->upsert(
            array_map(fn (Mark $mark) => [
                'student_id' => $marks->studentId(),
                'school_year_id' => $marks->schoolYearId(),
                'period' => $mark->period,
                'kind' => $mark->kind->value,
                'literal' => $mark->literal?->value,
                'description' => $mark->description,
                'incomplete' => $mark->incomplete,
                'created_at' => now(),
                'updated_at' => now(),
            ], $marks->marks()),
            ['student_id', 'school_year_id', 'period', 'kind'],
            ['literal', 'description', 'incomplete', 'updated_at'],
        );
    }
}
