<?php

declare(strict_types=1);

namespace AulaX\AcademicCalendar\Application\TermEditability;

use AulaX\AcademicCalendar\Domain\SchoolYearId;
use AulaX\AcademicCalendar\Domain\SchoolYearRepository;
use AulaX\AcademicCalendar\Domain\TermNumber;

/**
 * The public API of this module for SPEC-011 (CAL-R05): whether the grades
 * of a term may change now, for someone who has (or not) the
 * editar-notas-lapso-cerrado permission. The caller resolves the
 * permission, so this module does not depend on how permissions are kept.
 *
 * Denies by default: a year that does not exist in the active institution
 * (or of another one, under Row Level Security) admits no changes.
 */
final readonly class TermEditabilityQuery
{
    public function __construct(private SchoolYearRepository $years) {}

    public function __invoke(int $schoolYearId, int $term, bool $hasClosedTermPermission): bool
    {
        if ($schoolYearId <= 0) {
            return false;
        }

        $year = $this->years->byId(SchoolYearId::fromInt($schoolYearId));

        return $year !== null && $year->allowsGradeChanges(TermNumber::of($term), $hasClosedTermPermission);
    }
}
