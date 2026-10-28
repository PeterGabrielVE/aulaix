<?php

declare(strict_types=1);

namespace AulaX\Enrollment\Application\Roster;

final readonly class SectionRoster
{
    /**
     * @param  list<RosterStudent>  $students  By family and given names.
     */
    public function __construct(
        public int $schoolYearId,
        public SectionOverview $section,
        public array $students,
    ) {}
}
