<?php

declare(strict_types=1);

namespace AulaX\Evaluation\Application\Pending;

final readonly class PendingGradesRow
{
    /**
     * @param  ?string  $teacher  Who teaches the subject or guides the section; null when nobody is assigned.
     * @param  string  $item  The activity, «Sin plan de evaluación», «Literal» or «Informe».
     * @param  int  $students  How many students it lacks.
     */
    public function __construct(
        public ?string $teacher,
        public string $section,
        public ?string $subject,
        public string $item,
        public int $students,
    ) {}
}
