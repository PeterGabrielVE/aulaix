<?php

declare(strict_types=1);

namespace AulaX\AcademicStructure\Domain;

use AulaX\Shared\Domain\BusinessRuleViolation;

/**
 * How a school year of the institution is organized (SPEC-008): its
 * sections, the curriculum of each grade and its coordinators. One
 * aggregate per year, because its rules cross those pieces: a subject is
 * taught only if it is in the curriculum, taking it out takes its teachers
 * out, and the state of the year decides what may change (EAC-R07).
 *
 * Whether a section has students or a subject has grades (SPEC-010, 011)
 * comes from the caller, already resolved.
 */
final class SchoolYearStructure
{
    /**
     * @param  list<string>  $offeredLevels  Levels of the institution (SPEC-006).
     * @param  list<Section>  $sections
     * @param  array<string, list<int>>  $curriculum  Grade => subject ids.
     * @param  list<CoordinatorAssignment>  $coordinators
     */
    private function __construct(
        private int $schoolYearId,
        private YearStatus $status,
        private array $offeredLevels,
        private array $sections,
        private array $curriculum,
        private array $coordinators,
    ) {}

    /**
     * @param  list<string>  $offeredLevels
     */
    public static function empty(int $schoolYearId, YearStatus $status, array $offeredLevels): self
    {
        return new self($schoolYearId, $status, $offeredLevels, [], [], []);
    }

    /**
     * @param  list<string>  $offeredLevels
     * @param  list<Section>  $sections
     * @param  array<string, list<int>>  $curriculum
     * @param  list<CoordinatorAssignment>  $coordinators
     */
    public static function reconstitute(int $schoolYearId, YearStatus $status, array $offeredLevels, array $sections, array $curriculum, array $coordinators): self
    {
        return new self($schoolYearId, $status, $offeredLevels, $sections, $curriculum, $coordinators);
    }

    public function schoolYearId(): int
    {
        return $this->schoolYearId;
    }

    public function status(): YearStatus
    {
        return $this->status;
    }

    /**
     * @return list<Section> In creation order.
     */
    public function sections(): array
    {
        return $this->sections;
    }

    /**
     * @throws SectionNotFound
     */
    public function section(int $id): Section
    {
        foreach ($this->sections as $section) {
            if ($section->id() === $id) {
                return $section;
            }
        }

        throw SectionNotFound::create();
    }

    /**
     * @return list<int> Subject ids.
     */
    public function curriculumOf(Grade $grade): array
    {
        return $this->curriculum[$grade->value] ?? [];
    }

    /**
     * @return array<string, list<int>> Grade => subject ids.
     */
    public function curricula(): array
    {
        return $this->curriculum;
    }

    /**
     * @return list<CoordinatorAssignment>
     */
    public function coordinators(): array
    {
        return $this->coordinators;
    }

    public function isEmpty(): bool
    {
        return $this->sections === [] && array_filter($this->curriculum) === [];
    }

    /**
     * EAC-R02.1 – R02.3.
     *
     * @throws BusinessRuleViolation
     */
    public function createSection(Grade $grade, SectionLetter $letter, Capacity $capacity): Section
    {
        $this->assertWritable();
        $this->assertOffered($grade);

        foreach ($this->sections as $section) {
            if ($section->grade() === $grade && $section->letter()->equals($letter)) {
                throw BusinessRuleViolation::onField('letter', "Ya hay una sección {$grade->name()} {$letter->value} en este año escolar.");
            }
        }

        $section = new Section(null, $grade, $letter, $capacity);
        $this->sections[] = $section;

        return $section;
    }

    /**
     * Never below the students already enrolled (EAC-R02.5, R02.6).
     *
     * @throws BusinessRuleViolation
     */
    public function changeCapacity(int $sectionId, Capacity $capacity, int $enrolled): void
    {
        $this->assertWritable();
        $section = $this->section($sectionId);

        if ($capacity->value < $enrolled) {
            throw BusinessRuleViolation::onField('capacity', "La sección tiene {$enrolled} estudiantes inscritos: el cupo no puede ser menor.");
        }

        $section->changeCapacity($capacity);
    }

    /**
     * With its teacher assignments (EAC-R02.7); not from an active year while
     * it has students (EAC-R07.3), and never once it has enrollments, current
     * or not (SPEC-010 MAT-R10.1).
     *
     * @return Section The removed section.
     *
     * @throws StructureInUse
     */
    public function removeSection(int $sectionId, bool $inUse, bool $hasEnrollments = false): Section
    {
        $this->assertWritable();
        $section = $this->section($sectionId);

        if ($hasEnrollments || ($inUse && $this->status === YearStatus::Active)) {
            throw StructureInUse::section();
        }

        $this->sections = array_values(array_filter($this->sections, fn (Section $kept) => $kept !== $section));

        return $section;
    }

    /**
     * The subjects of a grade this year (EAC-R03): of the grade's level, each
     * once. The ones taken out leave their sections without teacher
     * (EAC-R03.4), unless they have grades in an active year (EAC-R07.3).
     *
     * @param  list<int>  $subjectIds
     * @param  array<int, string>  $subjectLevels  Subject id => level code, from the catalog.
     * @param  list<int>  $inUse  Subjects of this grade that have grades.
     *
     * @throws BusinessRuleViolation
     * @throws StructureInUse
     */
    public function setCurriculum(Grade $grade, array $subjectIds, array $subjectLevels, array $inUse): void
    {
        $this->assertWritable();
        $this->assertOffered($grade);

        foreach ($subjectIds as $subjectId) {
            if (($subjectLevels[$subjectId] ?? null) !== $grade->level()) {
                throw BusinessRuleViolation::onField('subjects', 'Esa materia no es de este nivel.');
            }
        }

        if (count($subjectIds) !== count(array_unique($subjectIds))) {
            throw BusinessRuleViolation::onField('subjects', 'Una materia aparece dos veces en el pensum.');
        }

        $removed = array_values(array_diff($this->curriculumOf($grade), $subjectIds));

        if ($this->status === YearStatus::Active && array_intersect($removed, $inUse) !== []) {
            throw StructureInUse::subject();
        }

        foreach ($this->sections as $section) {
            if ($section->grade() === $grade) {
                $section->dropSubjects($removed);
            }
        }

        $this->curriculum[$grade->value] = array_values($subjectIds);
    }

    /**
     * Sections (letter and capacity) and curriculum of another year, without
     * people, for the grades the institution offers now (EAC-R04).
     *
     * @throws BusinessRuleViolation
     */
    public function copyFrom(self $source): void
    {
        if ($this->status !== YearStatus::Planned) {
            throw BusinessRuleViolation::onField('school_year', 'Solo se copia la estructura a un año escolar planificado.');
        }

        if (! $this->isEmpty()) {
            throw BusinessRuleViolation::onField('school_year', 'El año destino ya tiene estructura.');
        }

        foreach ($source->sections() as $section) {
            if ($this->offers($section->grade())) {
                $this->sections[] = $section->blankCopy();
            }
        }

        foreach ($source->curricula() as $grade => $subjectIds) {
            if ($this->offers(Grade::from($grade))) {
                $this->curriculum[$grade] = $subjectIds;
            }
        }
    }

    /**
     * Who teaches a subject of the curriculum in a section, replacing whoever
     * did (EAC-R05.1, R05.3, R05.4), in any level (EAC-R05.7).
     *
     * @throws BusinessRuleViolation
     */
    public function assignTeacher(int $sectionId, int $subjectId, int $accountId): void
    {
        $this->assertWritable();
        $section = $this->section($sectionId);

        if (! in_array($subjectId, $this->curriculumOf($section->grade()), true)) {
            throw BusinessRuleViolation::onField('subject', 'La materia no está en el pensum del grado.');
        }

        $section->assignTeacher($subjectId, $accountId);
    }

    /**
     * The subject stays without teacher in the section (EAC-R05.5).
     */
    public function removeTeacher(int $sectionId, int $subjectId): void
    {
        $this->assertWritable();
        $this->section($sectionId)->removeTeacher($subjectId);
    }

    /**
     * The guide teacher of a section, replacing the one before; null removes
     * it (EAC-R06.1, R06.5).
     */
    public function setGuide(int $sectionId, ?int $accountId): void
    {
        $this->assertWritable();
        $this->section($sectionId)->setGuide($accountId);
    }

    /**
     * A coordinator of a level of the institution, of all of it or some of
     * its grades (EAC-R06.3); several per level (EAC-R06.6). Assigning a
     * person again in the same level replaces what they coordinate there.
     *
     * @throws BusinessRuleViolation
     */
    public function assignCoordinator(CoordinatorAssignment $assignment): void
    {
        $this->assertWritable();

        if (! in_array($assignment->level, $this->offeredLevels, true)) {
            throw BusinessRuleViolation::onField('level', 'Ese nivel no es del plantel.');
        }

        foreach ($assignment->grades as $grade) {
            if ($grade->level() !== $assignment->level) {
                throw BusinessRuleViolation::onField('grades', 'Esos grados no son de ese nivel.');
            }
        }

        $this->removeCoordinatorOf($assignment->accountId, $assignment->level);
        $this->coordinators[] = $assignment;
    }

    /**
     * EAC-R06.5.
     */
    public function removeCoordinator(int $accountId, string $level): void
    {
        $this->assertWritable();
        $this->removeCoordinatorOf($accountId, $level);
    }

    private function removeCoordinatorOf(int $accountId, string $level): void
    {
        $this->coordinators = array_values(array_filter(
            $this->coordinators,
            fn (CoordinatorAssignment $coordinator) => ! ($coordinator->accountId === $accountId && $coordinator->level === $level),
        ));
    }

    private function offers(Grade $grade): bool
    {
        return in_array($grade->level(), $this->offeredLevels, true);
    }

    /**
     * @throws BusinessRuleViolation
     */
    private function assertOffered(Grade $grade): void
    {
        if ($this->offeredLevels === []) {
            throw BusinessRuleViolation::onField('grade', 'Completa los niveles del plantel antes de crear secciones.');
        }

        if (! $this->offers($grade)) {
            throw BusinessRuleViolation::onField('grade', 'Ese grado no es de los niveles del plantel.');
        }
    }

    /**
     * @throws BusinessRuleViolation
     */
    private function assertWritable(): void
    {
        if ($this->status === YearStatus::Closed) {
            throw BusinessRuleViolation::onField('school_year', 'El año escolar está cerrado.');
        }
    }
}
