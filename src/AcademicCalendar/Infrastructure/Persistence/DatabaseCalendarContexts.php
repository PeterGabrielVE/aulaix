<?php

declare(strict_types=1);

namespace AulaX\AcademicCalendar\Infrastructure\Persistence;

use AulaX\AcademicCalendar\Application\CalendarContext\CalendarContext;
use AulaX\AcademicCalendar\Application\CalendarContext\CalendarContexts;
use AulaX\AcademicCalendar\Domain\SchoolYearStatus;
use AulaX\AcademicCalendar\Domain\TermStatus;
use Illuminate\Support\Facades\DB;

/**
 * One query: the active year with its open term, and whether a year is
 * planned. Row Level Security limits it to the open institution.
 */
final class DatabaseCalendarContexts implements CalendarContexts
{
    public function current(): CalendarContext
    {
        $row = DB::selectOne(<<<'SQL'
            SELECT active.id,
                   EXTRACT(YEAR FROM active.starts_on)::int AS starts,
                   EXTRACT(YEAR FROM active.ends_on)::int AS ends,
                   (SELECT number FROM terms WHERE terms.school_year_id = active.id AND terms.status = ?) AS open_term,
                   EXISTS (SELECT 1 FROM school_years WHERE status = ?) AS has_planned
            FROM (SELECT 1) AS anchor
            LEFT JOIN school_years AS active ON active.status = ?
            SQL, [TermStatus::Open->value, SchoolYearStatus::Planned->value, SchoolYearStatus::Active->value]);

        return new CalendarContext(
            $row->id === null ? null : (int) $row->id,
            $row->id === null ? null : "{$row->starts}-{$row->ends}",
            $row->open_term === null ? null : (int) $row->open_term,
            (bool) $row->has_planned,
        );
    }
}
