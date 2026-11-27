<?php

declare(strict_types=1);

namespace AulaX\Evaluation\Infrastructure\Persistence;

use AulaX\Evaluation\Domain\GradeMark;
use AulaX\Evaluation\Domain\PlanOwner;
use AulaX\Evaluation\Domain\Revision;
use AulaX\Evaluation\Domain\RevisionRepository;
use AulaX\Evaluation\Domain\Score;
use Illuminate\Support\Facades\DB;

/**
 * The revisions of the open institution, through the query builder (Row
 * Level Security filters every query). A revision cleared is deleted; one
 * per student, subject and year of a section, or per student of a group.
 */
final class EloquentRevisionRepository implements RevisionRepository
{
    public function revisionsOf(int $schoolYearId, int $subjectId, PlanOwner $owner, array $studentIds): array
    {
        $rows = DB::table('grade_revisions')
            ->whereIn('student_id', $studentIds)
            ->when(
                $owner->isSection(),
                fn ($query) => $query->where('school_year_id', $schoolYearId)->where('subject_id', $subjectId)->whereNotNull('section_id'),
                fn ($query) => $query->where('pending_group_id', $owner->pendingGroupId),
            )
            ->get()
            ->keyBy('student_id');
        $revisions = [];

        foreach ($studentIds as $studentId) {
            $row = $rows[$studentId] ?? null;
            $mark = match (true) {
                $row === null => null,
                (bool) $row->not_presented => GradeMark::notPresented(),
                default => GradeMark::score(Score::fromHundredths((int) $row->score)),
            };
            $revisions[$studentId] = Revision::of($row === null ? null : (int) $row->id, $schoolYearId, $subjectId, $studentId, $owner, $mark);
        }

        return $revisions;
    }

    public function saveRevision(Revision $revision): void
    {
        $mark = $revision->mark();

        if ($mark === null) {
            if ($revision->id() !== null) {
                DB::table('grade_revisions')->where('id', $revision->id())->delete();
            }

            return;
        }

        $row = [
            'score' => $mark->scoreOrNull()?->hundredths,
            'not_presented' => $mark->isNotPresented(),
            'updated_at' => now(),
        ];

        if ($revision->id() !== null) {
            DB::table('grade_revisions')->where('id', $revision->id())->update($row);

            return;
        }

        $revision->identify((int) DB::table('grade_revisions')->insertGetId([
            ...$row,
            'school_year_id' => $revision->schoolYearId(),
            'subject_id' => $revision->subjectId(),
            'student_id' => $revision->studentId(),
            'section_id' => $revision->owner()->sectionId,
            'pending_group_id' => $revision->owner()->pendingGroupId,
            'created_at' => now(),
        ]));
    }
}
