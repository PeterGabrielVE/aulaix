<?php

declare(strict_types=1);

namespace AulaX\AcademicCalendar\Domain;

/**
 * The rules across the school years of one institution, which belong to no
 * year in particular: a single planned year (CAL-R01.6), a single active
 * year (CAL-R02.1, R02.3) and no overlapping dates, whatever the other
 * year's state (CAL-R01.9). Pure: the use case hands it the other years,
 * read under the institution's calendar lock (ADR 0007).
 */
final class SchoolYearSchedule
{
    /**
     * @param  list<SchoolYear>  $others  The institution's other years.
     */
    public function assertCanPlan(SchoolYear $year, array $others): void
    {
        foreach ($others as $other) {
            if ($other->status() === SchoolYearStatus::Planned) {
                throw CalendarRuleViolation::onField('starts_on', 'Ya hay un año planificado en este plantel.');
            }
        }

        $this->assertDatesFit($year, $others);
    }

    /**
     * @param  list<SchoolYear>  $others
     */
    public function assertDatesFit(SchoolYear $year, array $others): void
    {
        foreach ($others as $other) {
            if ($year->dates()->overlaps($other->dates())) {
                throw CalendarRuleViolation::onField('starts_on', "Las fechas se solapan con el año {$other->name()}.");
            }
        }
    }

    /**
     * @param  list<SchoolYear>  $others
     */
    public function assertCanActivate(array $others): void
    {
        foreach ($others as $other) {
            if ($other->status() === SchoolYearStatus::Active) {
                throw CalendarRuleViolation::onField('status', 'Ya hay un año activo: ciérralo antes de activar otro.');
            }
        }
    }
}
