<?php

declare(strict_types=1);

namespace AulaX\AcademicStructure\Domain;

/**
 * A section of a grade in a school year (EAC-R02): its letter, its capacity,
 * its guide teacher and who teaches each subject of the curriculum, at most
 * one per subject (EAC-R05.4). Changed only through its SchoolYearStructure.
 */
final class Section
{
    /**
     * @param  array<int, int>  $teachers  Subject id => account id.
     */
    public function __construct(
        private ?int $id,
        private Grade $grade,
        private SectionLetter $letter,
        private Capacity $capacity,
        private ?int $guideAccountId = null,
        private array $teachers = [],
    ) {}

    public function id(): ?int
    {
        return $this->id;
    }

    public function identify(int $id): void
    {
        $this->id = $id;
    }

    public function grade(): Grade
    {
        return $this->grade;
    }

    public function letter(): SectionLetter
    {
        return $this->letter;
    }

    public function capacity(): Capacity
    {
        return $this->capacity;
    }

    public function guideAccountId(): ?int
    {
        return $this->guideAccountId;
    }

    /**
     * @return array<int, int> Subject id => account id.
     */
    public function teachers(): array
    {
        return $this->teachers;
    }

    public function changeCapacity(Capacity $capacity): void
    {
        $this->capacity = $capacity;
    }

    /**
     * Replaces whoever taught the subject (EAC-R05.4).
     */
    public function assignTeacher(int $subjectId, int $accountId): void
    {
        $this->teachers[$subjectId] = $accountId;
    }

    public function removeTeacher(int $subjectId): void
    {
        unset($this->teachers[$subjectId]);
    }

    public function setGuide(?int $accountId): void
    {
        $this->guideAccountId = $accountId;
    }

    /**
     * Without the teachers of these subjects, taken out of the curriculum
     * (EAC-R03.4).
     *
     * @param  list<int>  $subjectIds
     */
    public function dropSubjects(array $subjectIds): void
    {
        foreach ($subjectIds as $subjectId) {
            unset($this->teachers[$subjectId]);
        }
    }

    /**
     * The same section for another year: grade, letter and capacity only
     * (EAC-R04.1).
     */
    public function blankCopy(): self
    {
        return new self(null, $this->grade, $this->letter, $this->capacity);
    }
}
