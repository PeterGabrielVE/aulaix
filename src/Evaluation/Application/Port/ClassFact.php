<?php

declare(strict_types=1);

namespace AulaX\Evaluation\Application\Port;

/**
 * A subject of the curriculum of a section, in a school year.
 */
final readonly class ClassFact
{
    /**
     * @param  string  $section  "3.er año A".
     * @param  string  $level  inicial, primaria or media_general.
     */
    public function __construct(
        public int $sectionId,
        public int $schoolYearId,
        public string $section,
        public string $grade,
        public string $level,
        public int $subjectId,
        public string $subject,
    ) {}
}
