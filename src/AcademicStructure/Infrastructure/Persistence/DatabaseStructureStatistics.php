<?php

declare(strict_types=1);

namespace AulaX\AcademicStructure\Infrastructure\Persistence;

use AulaX\AcademicStructure\Application\Port\StructureStatistics;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Aggregate queries over the year's sections, curriculum and assignments.
 * Row Level Security limits them to the open institution.
 */
final class DatabaseStructureStatistics implements StructureStatistics
{
    public function summary(int $schoolYearId, ?array $grades): array
    {
        $sections = $this->sections($schoolYearId, $grades)
            ->selectRaw('count(*) AS sections, coalesce(sum(capacity), 0) AS capacity, count(*) FILTER (WHERE guide_account_id IS NULL) AS without_guide')
            ->first();

        $withoutTeacher = $this->sections($schoolYearId, $grades)
            ->join('curriculum_subjects', function ($join) {
                $join->on('curriculum_subjects.school_year_id', '=', 'sections.school_year_id')
                    ->on('curriculum_subjects.grade', '=', 'sections.grade');
            })
            ->leftJoin('teaching_assignments', function ($join) {
                $join->on('teaching_assignments.section_id', '=', 'sections.id')
                    ->on('teaching_assignments.subject_id', '=', 'curriculum_subjects.subject_id');
            })
            ->whereNull('teaching_assignments.account_id')
            ->count();

        return [
            'sections' => (int) $sections->sections,
            'capacity' => (int) $sections->capacity,
            'withoutGuide' => (int) $sections->without_guide,
            'withoutTeacher' => $withoutTeacher,
            'gradesWithSections' => $this->sections($schoolYearId, $grades)->distinct()->pluck('grade')->all(),
        ];
    }

    public function assignedAccounts(int $schoolYearId, ?array $grades): array
    {
        $teachers = $this->sections($schoolYearId, $grades)
            ->join('teaching_assignments', 'teaching_assignments.section_id', '=', 'sections.id')
            ->whereNotNull('teaching_assignments.account_id')
            ->pluck('teaching_assignments.account_id');

        $guides = $this->sections($schoolYearId, $grades)
            ->whereNotNull('guide_account_id')
            ->pluck('guide_account_id');

        return array_map(intval(...), [...$teachers->all(), ...$guides->all()]);
    }

    public function coordinationsOf(int $schoolYearId, int $accountId): array
    {
        return DB::table('coordinator_assignments')
            ->where('school_year_id', $schoolYearId)
            ->where('account_id', $accountId)
            ->get(['level', 'grade'])
            ->map(fn (object $row) => ['level' => $row->level, 'grade' => $row->grade])
            ->all();
    }

    /**
     * @param  ?list<string>  $grades
     */
    private function sections(int $schoolYearId, ?array $grades): Builder
    {
        return DB::table('sections')
            ->where('sections.school_year_id', $schoolYearId)
            ->when($grades !== null, fn (Builder $query) => $query->whereIn('sections.grade', $grades));
    }
}
