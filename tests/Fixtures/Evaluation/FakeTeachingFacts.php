<?php

namespace Tests\Fixtures\Evaluation;

use AulaX\Evaluation\Application\Port\ClassFact;
use AulaX\Evaluation\Application\Port\SectionInfo;
use AulaX\Evaluation\Application\Port\TeachingFacts;

/**
 * Who teaches, guides and coordinates what, and the sections and curricula,
 * as a test sets them up.
 */
final class FakeTeachingFacts implements TeachingFacts
{
    /** @var list<array{int, int, int}> Account, section, subject. */
    public array $teaching = [];

    /** @var list<array{int, int}> Account, section. */
    public array $guiding = [];

    /** @var array<int, list<string>> Account => grades. */
    public array $coordinated = [];

    /** @var array<int, SectionInfo> */
    public array $sections = [];

    /** @var array<string, list<int>> "year:grade" => subject ids. */
    public array $curricula = [];

    /** @var array<int, string> Subject id => name. */
    public array $subjectNames = [4 => 'Matemática', 5 => 'Física', 6 => 'Química', 7 => 'Lengua'];

    /** @var array{teachers: array<int, array<int, string>>, guides: array<int, string>} */
    public array $staff = ['teachers' => [], 'guides' => []];

    /** @var list<ClassFact> Of the active year 2, by section. */
    public array $classes = [];

    public function teaches(int $accountId, int $sectionId, int $subjectId): bool
    {
        return in_array([$accountId, $sectionId, $subjectId], $this->teaching, true);
    }

    public function guides(int $accountId, int $sectionId): bool
    {
        return in_array([$accountId, $sectionId], $this->guiding, true);
    }

    public function coordinatedGrades(int $accountId): array
    {
        return $this->coordinated[$accountId] ?? [];
    }

    public function section(int $schoolYearId, int $sectionId): ?SectionInfo
    {
        $section = $this->sections[$sectionId] ?? null;

        return $section?->schoolYearId === $schoolYearId ? $section : null;
    }

    public function sectionsOf(int $schoolYearId): array
    {
        return array_values(array_filter($this->sections, fn (SectionInfo $section) => $section->schoolYearId === $schoolYearId));
    }

    public function curriculum(int $schoolYearId, string $grade): array
    {
        return $this->curricula["{$schoolYearId}:{$grade}"] ?? [];
    }

    public function subjectNames(string $level): array
    {
        return $this->subjectNames;
    }

    public function taughtClasses(int $accountId): array
    {
        return array_values(array_filter($this->classes, fn (ClassFact $class) => $this->teaches($accountId, $class->sectionId, $class->subjectId)));
    }

    public function classesOf(int $schoolYearId): array
    {
        return array_values(array_filter($this->classes, fn (ClassFact $class) => $class->schoolYearId === $schoolYearId));
    }

    public function staffNames(int $schoolYearId): array
    {
        return $this->staff;
    }
}
