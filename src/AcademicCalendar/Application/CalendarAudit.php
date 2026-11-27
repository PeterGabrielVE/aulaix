<?php

declare(strict_types=1);

namespace AulaX\AcademicCalendar\Application;

use AulaX\AcademicCalendar\Domain\SchoolYear;
use AulaX\AcademicCalendar\Domain\TermNumber;
use AulaX\Audit\Application\AuditEvent;
use AulaX\Audit\Application\AuditSubject;
use AulaX\Audit\Application\ChangeSet;
use AulaX\Audit\Application\FieldChange;

/**
 * The audit log entries of the school calendar (CAL-R07.1): dates, states
 * and the reason of a reopening, nothing personal.
 */
final class CalendarAudit
{
    public static function created(SchoolYear $year): AuditEvent
    {
        return AuditEvent::of('calendar.school_year.created', self::subject($year), ChangeSet::diff([], self::dates($year)));
    }

    /**
     * @param  array<string, string>  $before  self::dates() of the year before the change.
     */
    public static function datesChanged(array $before, SchoolYear $year): AuditEvent
    {
        return AuditEvent::of('calendar.school_year.dates_changed', self::subject($year), ChangeSet::diff($before, self::dates($year)));
    }

    public static function statusChanged(string $action, SchoolYear $year, string $from, string $to): AuditEvent
    {
        return AuditEvent::of("calendar.school_year.{$action}", self::subject($year), [FieldChange::of('status', $from, $to)]);
    }

    public static function term(string $action, SchoolYear $year, TermNumber $term, string $from, string $to, ?string $reason = null): AuditEvent
    {
        $changes = [FieldChange::of('term', null, $term->value), FieldChange::of('status', $from, $to)];

        if ($reason !== null) {
            $changes[] = FieldChange::of('reason', null, $reason);
        }

        return AuditEvent::of("calendar.term.{$action}", self::subject($year), $changes);
    }

    /**
     * The year's dates as form fields, so a change names what moved.
     *
     * @return array<string, string>
     */
    public static function dates(SchoolYear $year): array
    {
        $dates = [
            'starts_on' => $year->dates()->start->format('Y-m-d'),
            'ends_on' => $year->dates()->end->format('Y-m-d'),
        ];

        foreach ($year->terms() as $index => $term) {
            $dates["terms.{$index}.starts_on"] = $term->dates->start->format('Y-m-d');
            $dates["terms.{$index}.ends_on"] = $term->dates->end->format('Y-m-d');
        }

        return $dates;
    }

    private static function subject(SchoolYear $year): AuditSubject
    {
        return AuditSubject::of('school_year', (int) $year->id()?->value);
    }
}
