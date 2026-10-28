<?php

declare(strict_types=1);

namespace AulaX\AcademicStructure\Infrastructure\Persistence;

use AulaX\AcademicStructure\Application\Port\AssignmentDirectory;
use Illuminate\Support\Facades\DB;

/**
 * One indexed lookup each (teaching_assignments by account, section and
 * subject), under the open institution's Row Level Security.
 */
final class EloquentAssignmentDirectory implements AssignmentDirectory
{
    public function teaches(int $accountId, int $sectionId, int $subjectId, int $schoolYearId): bool
    {
        return DB::table('teaching_assignments')
            ->join('sections', 'sections.id', '=', 'teaching_assignments.section_id')
            ->where('teaching_assignments.account_id', $accountId)
            ->where('teaching_assignments.section_id', $sectionId)
            ->where('teaching_assignments.subject_id', $subjectId)
            ->where('sections.school_year_id', $schoolYearId)
            ->exists();
    }

    public function guides(int $accountId, int $sectionId, int $schoolYearId): bool
    {
        return DB::table('sections')
            ->where('id', $sectionId)
            ->where('guide_account_id', $accountId)
            ->where('school_year_id', $schoolYearId)
            ->exists();
    }

    public function sectionIdsOf(int $accountId, int $schoolYearId): array
    {
        return DB::table('sections')
            ->where('school_year_id', $schoolYearId)
            ->where(fn ($query) => $query
                ->where('guide_account_id', $accountId)
                ->orWhereExists(fn ($assigned) => $assigned
                    ->selectRaw('1')
                    ->from('teaching_assignments')
                    ->whereColumn('teaching_assignments.section_id', 'sections.id')
                    ->where('teaching_assignments.account_id', $accountId)))
            ->orderBy('id')
            ->pluck('id')
            ->map(fn (mixed $id) => (int) $id)
            ->all();
    }
}
