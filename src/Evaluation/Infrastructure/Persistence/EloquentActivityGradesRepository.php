<?php

declare(strict_types=1);

namespace AulaX\Evaluation\Infrastructure\Persistence;

use AulaX\Evaluation\Domain\Activity;
use AulaX\Evaluation\Domain\ActivityGrades;
use AulaX\Evaluation\Domain\ActivityGradesRepository;
use AulaX\Evaluation\Domain\GradeMark;
use AulaX\Evaluation\Domain\Score;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * The grades of the activities of the open institution, through the query
 * builder (Row Level Security filters every query). A grade is kept in
 * hundredths, NP as not_presented with no score.
 */
final class EloquentActivityGradesRepository implements ActivityGradesRepository
{
    public function forActivity(Activity $activity, int $sectionId): ActivityGrades
    {
        $id = $activity->id ?? throw new LogicException('Only a saved activity has grades.');
        $marks = [];

        foreach (DB::table('activity_grades')->where('activity_id', $id->value)->get() as $row) {
            $marks[(int) $row->student_id] = $row->not_presented ? GradeMark::notPresented() : GradeMark::score(Score::fromHundredths((int) $row->score));
        }

        return ActivityGrades::of($id, $sectionId, $activity->date, $marks);
    }

    public function saveGrades(ActivityGrades $grades): void
    {
        $activityId = $grades->activityId()->value;
        $marks = $grades->marks();

        DB::transaction(function () use ($activityId, $marks) {
            DB::table('activity_grades')->where('activity_id', $activityId)->whereNotIn('student_id', array_keys($marks))->delete();

            if ($marks === []) {
                return;
            }

            DB::table('activity_grades')->upsert(
                array_map(fn (int $studentId, GradeMark $mark) => [
                    'activity_id' => $activityId,
                    'student_id' => $studentId,
                    'score' => $mark->scoreOrNull()?->hundredths,
                    'not_presented' => $mark->isNotPresented(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ], array_keys($marks), $marks),
                ['activity_id', 'student_id'],
                ['score', 'not_presented', 'updated_at'],
            );
        });
    }

    public function gradedWithin(int $studentId, int $sectionId, DateTimeImmutable $from, ?DateTimeImmutable $until): bool
    {
        return DB::table('activity_grades')
            ->join('evaluation_activities', 'evaluation_activities.id', '=', 'activity_grades.activity_id')
            ->join('evaluation_plans', 'evaluation_plans.id', '=', 'evaluation_activities.plan_id')
            ->where('activity_grades.student_id', $studentId)
            ->where('evaluation_plans.section_id', $sectionId)
            ->where('evaluation_activities.date', '>=', $from->format('Y-m-d'))
            ->when($until !== null, fn ($query) => $query->where('evaluation_activities.date', '<', $until?->format('Y-m-d')))
            ->exists();
    }

    public function subjectsWithGrades(int $schoolYearId, array $sectionIds, array $subjectIds): array
    {
        if ($sectionIds === [] || $subjectIds === []) {
            return [];
        }

        return DB::table('activity_grades')
            ->join('evaluation_activities', 'evaluation_activities.id', '=', 'activity_grades.activity_id')
            ->join('evaluation_plans', 'evaluation_plans.id', '=', 'evaluation_activities.plan_id')
            ->where('evaluation_plans.school_year_id', $schoolYearId)
            ->whereIn('evaluation_plans.section_id', $sectionIds)
            ->whereIn('evaluation_plans.subject_id', $subjectIds)
            ->distinct()
            ->orderBy('evaluation_plans.subject_id')
            ->pluck('evaluation_plans.subject_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    public function withGrades(array $activityIds): array
    {
        return DB::table('activity_grades')
            ->whereIn('activity_id', $activityIds)
            ->distinct()
            ->orderBy('activity_id')
            ->pluck('activity_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }
}
