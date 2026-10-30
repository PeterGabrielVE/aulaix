<?php

declare(strict_types=1);

namespace AulaX\Evaluation\Infrastructure\Persistence;

use AulaX\Evaluation\Domain\PendingGroup;
use AulaX\Evaluation\Domain\PendingGroupRepository;
use Illuminate\Support\Facades\DB;

/**
 * The groups of pending subjects of the open institution, through the query
 * builder (Row Level Security filters every query). One per year, subject
 * and grade of origin.
 */
final class EloquentPendingGroupRepository implements PendingGroupRepository
{
    public function groupById(int $id): ?PendingGroup
    {
        $row = $id > 0 ? DB::table('pending_groups')->where('id', $id)->first() : null;

        return $row === null ? null : self::toDomain($row);
    }

    public function groupOf(int $schoolYearId, int $subjectId, string $originGrade): ?PendingGroup
    {
        $row = DB::table('pending_groups')
            ->where('school_year_id', $schoolYearId)
            ->where('subject_id', $subjectId)
            ->where('origin_grade', $originGrade)
            ->first();

        return $row === null ? null : self::toDomain($row);
    }

    public function groupsOfSubject(int $subjectId, string $originGrade): array
    {
        return DB::table('pending_groups')
            ->where('subject_id', $subjectId)
            ->where('origin_grade', $originGrade)
            ->orderBy('id')
            ->get()
            ->map(fn (object $row) => self::toDomain($row))
            ->all();
    }

    public function groupsOf(int $schoolYearId): array
    {
        return DB::table('pending_groups')
            ->where('school_year_id', $schoolYearId)
            ->orderBy('id')
            ->get()
            ->map(fn (object $row) => self::toDomain($row))
            ->all();
    }

    public function saveGroup(PendingGroup $group): void
    {
        if ($group->id() !== null) {
            DB::table('pending_groups')->where('id', $group->id())->update(['teacher_account_id' => $group->teacherAccountId(), 'updated_at' => now()]);

            return;
        }

        $group->identify((int) DB::table('pending_groups')->insertGetId([
            'school_year_id' => $group->schoolYearId(),
            'subject_id' => $group->subjectId(),
            'origin_grade' => $group->originGrade(),
            'teacher_account_id' => $group->teacherAccountId(),
            'created_at' => now(),
            'updated_at' => now(),
        ]));
    }

    private static function toDomain(object $row): PendingGroup
    {
        return PendingGroup::reconstitute(
            (int) $row->id,
            (int) $row->school_year_id,
            (int) $row->subject_id,
            $row->origin_grade,
            $row->teacher_account_id === null ? null : (int) $row->teacher_account_id,
        );
    }
}
