<?php

declare(strict_types=1);

namespace AulaX\AcademicStructure\Application\ShowStructure;

/**
 * The structure screen (EAC-R08.1): one school year of the institution and
 * what its forms need.
 */
final readonly class StructureView
{
    /**
     * @param  ?int  $schoolYearId  Null when the institution has no school year yet.
     * @param  list<array{id: int, name: string, status: string}>  $years  Newest first.
     * @param  list<array{code: string, name: string, level: string, stage: string, capacityCap: int}>  $grades  Of the institution's levels.
     * @param  list<SectionView>  $sections  By grade, then letter.
     * @param  array<string, list<array{id: int, name: string}>>  $curriculum  Grade => subjects.
     * @param  array<string, list<array{id: int, code: string, name: string}>>  $subjectOptions  Level => catalog subjects.
     * @param  list<array{accountId: int, level: string, grades: list<string>}>  $coordinators
     * @param  array<int, array{name: string, active: bool}>  $people  Everyone assigned, by account id.
     * @param  list<array{sectionId: int, subjectId: ?int, accountId: int}>  $orphans  Assignments (subject null: guide) of people no longer active (EAC-R08.5).
     * @param  list<array{id: int, name: string}>  $teacherCandidates
     * @param  list<array{id: int, name: string}>  $coordinatorCandidates
     */
    public function __construct(
        public ?int $schoolYearId,
        public ?string $status,
        public array $years,
        public array $grades,
        public array $sections,
        public array $curriculum,
        public array $subjectOptions,
        public array $coordinators = [],
        public array $people = [],
        public array $orphans = [],
        public array $teacherCandidates = [],
        public array $coordinatorCandidates = [],
    ) {}
}
