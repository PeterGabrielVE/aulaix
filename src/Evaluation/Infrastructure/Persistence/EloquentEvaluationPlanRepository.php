<?php

declare(strict_types=1);

namespace AulaX\Evaluation\Infrastructure\Persistence;

use AulaX\Evaluation\Domain\Activity;
use AulaX\Evaluation\Domain\ActivityId;
use AulaX\Evaluation\Domain\EvaluationPlan;
use AulaX\Evaluation\Domain\EvaluationPlanRepository;
use AulaX\Evaluation\Domain\PlanChanged;
use AulaX\Evaluation\Domain\PlanOwner;
use AulaX\Evaluation\Domain\Weight;
use DateTimeImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The plans of the open institution, through the query builder (Row Level
 * Security filters every query). One row per plan: a second first save
 * collides with the unique index, and an edit locks the row and checks that
 * the activities it keeps are still there; either way, PlanChanged.
 */
final class EloquentEvaluationPlanRepository implements EvaluationPlanRepository
{
    /**
     * The unique indexes of a plan per sheet and per group and term (ADR 0013).
     */
    private const array ONE_PER_OWNER = ['evaluation_plans_one_per_sheet', 'evaluation_plans_one_per_group_term'];

    public function planOf(int $schoolYearId, int $sectionId, int $subjectId, int $term): ?EvaluationPlan
    {
        $row = DB::table('evaluation_plans')
            ->where('school_year_id', $schoolYearId)
            ->where('section_id', $sectionId)
            ->where('subject_id', $subjectId)
            ->where('term', $term)
            ->first();

        return $row === null ? null : $this->toDomain(collect([$row]))[0];
    }

    public function plansOfSubject(int $schoolYearId, int $subjectId, int $term): array
    {
        return $this->toDomain(DB::table('evaluation_plans')
            ->where('school_year_id', $schoolYearId)
            ->where('subject_id', $subjectId)
            ->where('term', $term)
            ->whereNotNull('section_id')
            ->orderBy('id')
            ->get());
    }

    public function planOfGroup(int $pendingGroupId, int $term): ?EvaluationPlan
    {
        $row = DB::table('evaluation_plans')->where('pending_group_id', $pendingGroupId)->where('term', $term)->first();

        return $row === null ? null : $this->toDomain(collect([$row]))[0];
    }

    public function planOfActivity(ActivityId $activityId): ?EvaluationPlan
    {
        $row = DB::table('evaluation_plans')
            ->whereIn('id', DB::table('evaluation_activities')->select('plan_id')->where('id', $activityId->value))
            ->first();

        return $row === null ? null : $this->toDomain(collect([$row]))[0];
    }

    public function save(EvaluationPlan $plan): void
    {
        DB::transaction(function () use ($plan) {
            $planId = $plan->id() ?? $this->insertPlan($plan);

            if ($plan->id() !== null && DB::table('evaluation_plans')->where('id', $planId)->lockForUpdate()->doesntExist()) {
                throw PlanChanged::create();
            }

            $kept = array_values(array_filter(array_map(fn (Activity $activity) => $activity->id?->value, $plan->activities())));
            DB::table('evaluation_activities')->where('plan_id', $planId)->whereNotIn('id', $kept)->delete();

            foreach ($plan->activities() as $position => $activity) {
                $row = [
                    'name' => $activity->name,
                    'date' => $activity->date->format('Y-m-d'),
                    'weight' => $activity->weight->hundredths,
                    'position' => $position,
                    'updated_at' => now(),
                ];

                if ($activity->id === null) {
                    DB::table('evaluation_activities')->insert([...$row, 'plan_id' => $planId, 'created_at' => now()]);
                } elseif (DB::table('evaluation_activities')->where('plan_id', $planId)->where('id', $activity->id->value)->update($row) === 0) {
                    throw PlanChanged::create();
                }
            }

            $plan->identify($planId);
        });
    }

    private function insertPlan(EvaluationPlan $plan): int
    {
        try {
            // A savepoint, so a collision leaves the request's transaction usable.
            return DB::transaction(fn () => (int) DB::table('evaluation_plans')->insertGetId([
                'school_year_id' => $plan->schoolYearId(),
                'section_id' => $plan->owner()->sectionId,
                'pending_group_id' => $plan->owner()->pendingGroupId,
                'subject_id' => $plan->subjectId(),
                'term' => $plan->term(),
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        } catch (QueryException $exception) {
            if (array_filter(self::ONE_PER_OWNER, fn (string $index) => str_contains($exception->getMessage(), $index)) !== []) {
                throw PlanChanged::create();
            }

            throw $exception;
        }
    }

    /**
     * @param  Collection<int, object>  $rows
     * @return list<EvaluationPlan>
     */
    private function toDomain(Collection $rows): array
    {
        $activities = DB::table('evaluation_activities')
            ->whereIn('plan_id', $rows->pluck('id'))
            ->orderBy('position')
            ->orderBy('id')
            ->get()
            ->groupBy('plan_id');

        return $rows->map(fn (object $row) => EvaluationPlan::reconstitute(
            (int) $row->id,
            (int) $row->school_year_id,
            $row->section_id === null ? PlanOwner::pendingGroup((int) $row->pending_group_id) : PlanOwner::section((int) $row->section_id),
            (int) $row->subject_id,
            (int) $row->term,
            ($activities[$row->id] ?? collect())->map(fn (object $activity) => new Activity(
                ActivityId::fromInt((int) $activity->id),
                $activity->name,
                new DateTimeImmutable($activity->date),
                Weight::fromHundredths((int) $activity->weight),
            ))->values()->all(),
        ))->values()->all();
    }
}
