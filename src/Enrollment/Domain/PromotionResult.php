<?php

declare(strict_types=1);

namespace AulaX\Enrollment\Domain;

/**
 * The result of a school year and, promoted with pending subjects, one or
 * two of them (MAT-R06.5, R06.6).
 */
final readonly class PromotionResult
{
    /**
     * @param  list<int>  $pendingSubjects  Subject ids, in order.
     */
    private function __construct(
        public Outcome $outcome,
        public array $pendingSubjects,
    ) {}

    /**
     * @param  list<int>  $pendingSubjects
     *
     * @throws ResultNotAllowed
     */
    public static function of(Outcome $outcome, array $pendingSubjects): self
    {
        $pending = array_values(array_unique(array_map('intval', $pendingSubjects)));
        sort($pending);

        if ($outcome !== Outcome::PromotedWithPending && $pending !== []) {
            throw ResultNotAllowed::pendingSubjects('Solo un estudiante promovido con materia pendiente tiene materias pendientes.');
        }

        if ($outcome === Outcome::PromotedWithPending && $pending === []) {
            throw ResultNotAllowed::pendingSubjects('Indica las materias pendientes: una o dos.');
        }

        if (count($pending) > 2) {
            throw ResultNotAllowed::pendingSubjects('Con 3 o más materias reprobadas el estudiante repite.');
        }

        return new self($outcome, $pending);
    }
}
