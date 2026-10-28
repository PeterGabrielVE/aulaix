<?php

declare(strict_types=1);

namespace AulaX\Enrollment\Application\Roster;

use AulaX\Students\Application\Enrollable\EnrollableStudent;

final readonly class YearOverview
{
    /**
     * @param  ?int  $schoolYearId  Null when the institution has no school year yet.
     * @param  ?string  $status  planned, active or closed.
     * @param  list<array{id: int, name: string, status: string}>  $years  To choose from, newest first.
     * @param  list<SectionOverview>  $sections
     * @param  list<EnrollableStudent>  $unenrolled  Active students without a current enrollment in the year.
     */
    public function __construct(
        public ?int $schoolYearId,
        public ?string $status,
        public array $years,
        public array $sections,
        public array $unenrolled,
    ) {}
}
