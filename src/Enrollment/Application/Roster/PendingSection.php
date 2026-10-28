<?php

declare(strict_types=1);

namespace AulaX\Enrollment\Application\Roster;

final readonly class PendingSection
{
    /**
     * @param  list<string>  $students  Their names, only for whoever may see students.
     */
    public function __construct(
        public int $sectionId,
        public string $gradeName,
        public string $letter,
        public int $count,
        public array $students,
    ) {}
}
