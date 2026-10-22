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
     */
    public function __construct(
        public ?int $schoolYearId,
        public ?string $status,
        public array $years,
        public array $grades,
        public array $sections,
        public array $curriculum,
        public array $subjectOptions,
    ) {}
}
