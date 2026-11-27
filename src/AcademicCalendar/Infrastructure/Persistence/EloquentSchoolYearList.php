<?php

declare(strict_types=1);

namespace AulaX\AcademicCalendar\Infrastructure\Persistence;

use AulaX\AcademicCalendar\Application\ListSchoolYears\SchoolYearList;
use AulaX\AcademicCalendar\Application\ListSchoolYears\SchoolYearView;
use AulaX\AcademicCalendar\Application\ListSchoolYears\TermView;

/**
 * Row Level Security limits it to the active institution.
 */
final class EloquentSchoolYearList implements SchoolYearList
{
    public function all(): array
    {
        return SchoolYearModel::query()
            ->with('terms')
            ->orderByDesc('starts_on')
            ->get()
            ->map(fn (SchoolYearModel $year) => new SchoolYearView(
                (int) $year->id,
                self::day($year->starts_on, 0, 4).'-'.self::day($year->ends_on, 0, 4),
                self::day($year->starts_on),
                self::day($year->ends_on),
                $year->status,
                $year->terms->map(fn (TermModel $term) => new TermView(
                    (int) $term->number,
                    self::day($term->starts_on),
                    self::day($term->ends_on),
                    $term->status,
                ))->values()->all(),
            ))
            ->values()
            ->all();
    }

    private static function day(mixed $date, int $from = 0, int $length = 10): string
    {
        return substr((string) $date, $from, $length);
    }
}
