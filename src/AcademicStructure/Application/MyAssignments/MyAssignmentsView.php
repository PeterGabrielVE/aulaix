<?php

declare(strict_types=1);

namespace AulaX\AcademicStructure\Application\MyAssignments;

final readonly class MyAssignmentsView
{
    /**
     * @param  ?int  $schoolYearId  Null when there is no active year.
     * @param  list<array{sectionId: int, section: string, level: string, subjectId: int, subject: string}>  $teaching  Level: SPEC-011 links Media General to its sheet.
     * @param  list<array{sectionId: int, section: string, level: string}>  $guiding  Level: SPEC-011 links Primaria and Inicial to their literals or reports.
     */
    public function __construct(
        public ?int $schoolYearId,
        public array $teaching,
        public array $guiding,
    ) {}
}
