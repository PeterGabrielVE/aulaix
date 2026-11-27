<?php

declare(strict_types=1);

namespace AulaX\Enrollment\Application\Roster;

final readonly class PromotionSheet
{
    /**
     * @param  bool  $ready  The year is active with its third term closed (MAT-R06.1).
     * @param  list<PromotionRow>  $students  By name.
     * @param  list<string>  $outcomes  The results its level allows.
     * @param  list<array{id: int, name: string}>  $subjects  The curriculum, for the pending subjects.
     */
    public function __construct(
        public int $schoolYearId,
        public SectionOverview $section,
        public bool $ready,
        public array $students,
        public array $outcomes,
        public array $subjects,
    ) {}
}
