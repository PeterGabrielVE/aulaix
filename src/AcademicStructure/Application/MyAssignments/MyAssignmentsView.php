<?php

declare(strict_types=1);

namespace AulaX\AcademicStructure\Application\MyAssignments;

final readonly class MyAssignmentsView
{
    /**
     * @param  ?int  $schoolYearId  Null when there is no active year.
     * @param  list<array{sectionId: int, section: string, subjectId: int, subject: string}>  $teaching
     * @param  list<array{sectionId: int, section: string}>  $guiding
     */
    public function __construct(
        public ?int $schoolYearId,
        public array $teaching,
        public array $guiding,
    ) {}
}
