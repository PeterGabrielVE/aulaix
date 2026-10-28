<?php

declare(strict_types=1);

namespace AulaX\Enrollment\Domain;

use InvalidArgumentException;

/**
 * Where an enrollment puts a student: a section, of a grade, of a level.
 */
final readonly class Placement
{
    private function __construct(
        public int $sectionId,
        public string $grade,
        public Level $level,
    ) {}

    public static function of(int $sectionId, string $grade, string $level): self
    {
        if ($sectionId <= 0 || $grade === '') {
            throw new InvalidArgumentException('A placement needs a section and a grade.');
        }

        return new self($sectionId, $grade, Level::from($level));
    }

    public function sameGradeAs(self $other): bool
    {
        return $this->grade === $other->grade;
    }
}
