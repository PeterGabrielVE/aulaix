<?php

declare(strict_types=1);

namespace AulaX\Enrollment\Application;

use AulaX\Enrollment\Application\Port\CalendarFacts;
use AulaX\Enrollment\Application\Port\SectionFact;
use AulaX\Enrollment\Application\Port\StructureFacts;
use AulaX\Enrollment\Domain\NotEnrollable;
use AulaX\Enrollment\Domain\Placement;

/**
 * Where an enrollment can go: a section of a school year that is not closed
 * (MAT-R01.4, R01.5, R04.3, R04.5).
 */
final readonly class Placements
{
    public function __construct(
        private StructureFacts $structure,
        private CalendarFacts $calendar,
    ) {}

    /**
     * @throws NotEnrollable
     */
    public function openSection(int $schoolYearId, int $sectionId): SectionFact
    {
        if ($this->calendar->statusOf($schoolYearId) === 'closed') {
            throw NotEnrollable::closedYear();
        }

        return $this->section($schoolYearId, $sectionId) ?? throw NotEnrollable::sectionOfAnotherYear();
    }

    public function section(int $schoolYearId, int $sectionId): ?SectionFact
    {
        foreach ($this->structure->sectionsOf($schoolYearId) as $section) {
            if ($section->id === $sectionId) {
                return $section;
            }
        }

        return null;
    }

    public static function of(SectionFact $section): Placement
    {
        return Placement::of($section->id, $section->grade, $section->level);
    }
}
