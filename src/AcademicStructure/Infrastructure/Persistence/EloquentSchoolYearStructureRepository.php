<?php

declare(strict_types=1);

namespace AulaX\AcademicStructure\Infrastructure\Persistence;

use AulaX\AcademicStructure\Domain\Capacity;
use AulaX\AcademicStructure\Domain\CoordinatorAssignment;
use AulaX\AcademicStructure\Domain\Grade;
use AulaX\AcademicStructure\Domain\SchoolYearStructure;
use AulaX\AcademicStructure\Domain\SchoolYearStructureRepository;
use AulaX\AcademicStructure\Domain\Section;
use AulaX\AcademicStructure\Domain\SectionLetter;
use AulaX\AcademicStructure\Domain\YearStatus;
use AulaX\Shared\Domain\Conflict;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Sections keep their ids across saves (the enrollments and grades of
 * SPEC-010 and 011 will point at them); their teachers, the curriculum and
 * the coordinators are replaced whole: the aggregate is their only writer.
 */
final class EloquentSchoolYearStructureRepository implements SchoolYearStructureRepository
{
    private const string UNIQUE_VIOLATION = '23505';

    public function load(int $schoolYearId, YearStatus $status, array $offeredLevels): SchoolYearStructure
    {
        $sectionRows = DB::table('sections')->where('school_year_id', $schoolYearId)->orderBy('id')->get();
        $teachers = DB::table('teaching_assignments')
            ->whereIn('section_id', $sectionRows->pluck('id'))
            ->whereNotNull('account_id')
            ->orderBy('id')
            ->get()
            ->groupBy('section_id');

        $sections = $sectionRows->map(function (object $row) use ($teachers) {
            $grade = Grade::from($row->grade);

            return new Section(
                (int) $row->id,
                $grade,
                SectionLetter::fromString($row->letter),
                Capacity::of((int) $row->capacity, $row->capacity_reason, $grade),
                $row->guide_account_id === null ? null : (int) $row->guide_account_id,
                ($teachers[$row->id] ?? collect())->mapWithKeys(fn (object $assignment) => [(int) $assignment->subject_id => (int) $assignment->account_id])->all(),
            );
        })->all();

        $curriculum = [];

        foreach (DB::table('curriculum_subjects')->where('school_year_id', $schoolYearId)->orderBy('id')->get() as $row) {
            $curriculum[$row->grade][] = (int) $row->subject_id;
        }

        $coordinators = DB::table('coordinator_assignments')
            ->where('school_year_id', $schoolYearId)
            ->orderBy('id')
            ->get()
            ->groupBy(fn (object $row) => $row->account_id.'|'.$row->level)
            ->map(fn ($rows) => new CoordinatorAssignment(
                (int) $rows->first()->account_id,
                $rows->first()->level,
                $rows->pluck('grade')->filter()->map(fn (string $grade) => Grade::from($grade))->values()->all(),
            ))
            ->values()
            ->all();

        return SchoolYearStructure::reconstitute($schoolYearId, $status, $offeredLevels, array_values($sections), $curriculum, $coordinators);
    }

    public function yearOfSection(int $sectionId): ?int
    {
        $year = DB::table('sections')->where('id', $sectionId)->value('school_year_id');

        return $year === null ? null : (int) $year;
    }

    public function save(SchoolYearStructure $structure): void
    {
        try {
            DB::transaction(fn () => $this->write($structure));
        } catch (QueryException $exception) {
            if ($exception->getCode() === self::UNIQUE_VIOLATION) {
                throw new Conflict('Otra persona acaba de cambiar la estructura de este año. Vuelve a cargar la página.', previous: $exception);
            }

            throw $exception;
        }
    }

    private function write(SchoolYearStructure $structure): void
    {
        $year = $structure->schoolYearId();
        $kept = [];

        foreach ($structure->sections() as $section) {
            $attributes = [
                'grade' => $section->grade()->value,
                'letter' => $section->letter()->value,
                'capacity' => $section->capacity()->value,
                'capacity_reason' => $section->capacity()->reason,
                'guide_account_id' => $section->guideAccountId(),
                'updated_at' => now(),
            ];

            if ($section->id() === null) {
                $section->identify((int) DB::table('sections')->insertGetId([...$attributes, 'school_year_id' => $year, 'created_at' => now()]));
            } else {
                DB::table('sections')->where('id', $section->id())->update($attributes);
            }

            $kept[] = $section->id();
        }

        DB::table('sections')->where('school_year_id', $year)->whereNotIn('id', $kept)->delete();

        foreach ($structure->sections() as $section) {
            DB::table('teaching_assignments')->where('section_id', $section->id())->delete();
            DB::table('teaching_assignments')->insert(array_map(
                fn (int $subjectId, int $accountId) => ['section_id' => $section->id(), 'subject_id' => $subjectId, 'account_id' => $accountId, 'created_at' => now(), 'updated_at' => now()],
                array_keys($section->teachers()),
                $section->teachers(),
            ));
        }

        DB::table('curriculum_subjects')->where('school_year_id', $year)->delete();

        foreach ($structure->curricula() as $grade => $subjectIds) {
            DB::table('curriculum_subjects')->insert(array_map(
                fn (int $subjectId) => ['school_year_id' => $year, 'grade' => $grade, 'subject_id' => $subjectId],
                $subjectIds,
            ));
        }

        DB::table('coordinator_assignments')->where('school_year_id', $year)->delete();

        foreach ($structure->coordinators() as $coordinator) {
            $grades = $coordinator->grades === [] ? [null] : array_map(fn (Grade $grade) => $grade->value, $coordinator->grades);

            DB::table('coordinator_assignments')->insert(array_map(
                fn (?string $grade) => ['school_year_id' => $year, 'account_id' => $coordinator->accountId, 'level' => $coordinator->level, 'grade' => $grade, 'created_at' => now(), 'updated_at' => now()],
                $grades,
            ));
        }
    }
}
